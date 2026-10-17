<?php

declare(strict_types=1);

namespace Spartan\Tests\Unit;

use Spartan\AuthInterface;
use Spartan\Cache;
use Spartan\ExceptionHandler;
use Spartan\HttpResponseException;
use Spartan\Logger;
use Spartan\Middleware;
use Spartan\QueueDrivers\DatabaseQueueDriver;
use Spartan\Request;
use Spartan\Response;
use Spartan\Tests\Fixtures\BlockMiddleware;
use Spartan\Tests\Fixtures\EmailRequest;
use Spartan\Tests\Fixtures\PassMiddleware;
use Spartan\Tests\Fixtures\Trace;
use Spartan\Tests\Fixtures\UserModel;
use Spartan\Tests\TestCase;
use Spartan\Translation\Translator;
use Spartan\UploadedFile;
use Spartan\Validator;
use Spartan\View;

/**
 * Regression tests for the security / correctness audit fixes.
 */
final class HardeningTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Trace::reset();
    }

    // ─── Open redirect ───────────────────────────────────────────────────────

    /**
     * @dataProvider externalRedirects
     */
    public function test_external_redirect_targets_fall_back_to_home(string $target): void
    {
        $response = new Response();
        $response->redirect($target);

        $this->assertSame('/', $response->getHeaders()['Location']);
    }

    public static function externalRedirects(): array
    {
        return [
            'scheme-relative'    => ['//evil.com/x'],
            'backslash relative' => ['/\\evil.com'],
            'leading backslash'  => ['\\\\evil.com'],
            'host prefix'        => ['http://localhost:8000.evil.com/'],
            'userinfo'           => ['http://localhost:8000@evil.com/'],
            'other port'         => ['http://localhost:9999/'],
            'javascript'         => ['javascript:alert(1)'],
            'embedded newline'   => ["/ok\n//evil.com"],
        ];
    }

    /**
     * @dataProvider localRedirects
     */
    public function test_local_redirect_targets_are_kept(string $target): void
    {
        $response = new Response();
        $response->redirect($target);

        $this->assertSame($target, $response->getHeaders()['Location']);
    }

    public static function localRedirects(): array
    {
        return [
            'root'         => ['/'],
            'path'         => ['/dashboard?tab=1'],
            'relative'     => ['login'],
            'same origin'  => ['http://localhost:8000/account'],
        ];
    }

    // ─── Router ──────────────────────────────────────────────────────────────

    public function test_redirect_route_writes_to_the_current_response_in_worker_mode(): void
    {
        [$router] = $this->router('GET', '/old');
        $router->redirect('/old', '/new', 301);

        // Worker mode: a fresh Response per request.
        $fresh = new Response();
        $router->setResponse($fresh);
        $router->resolve();

        $this->assertSame('/new', $fresh->getHeaders()['Location'] ?? null);
        $this->assertSame(301, $fresh->getStatusCode());
    }

    public function test_middleware_code_after_next_runs_after_the_route(): void
    {
        [$router] = $this->router('GET', '/wrap');
        $router->get('/wrap', function () {
            Trace::$log[] = 'route';
            return 'body';
        }, [WrappingMiddleware::class]);

        $this->assertSame('[body]', $router->resolve());
        $this->assertSame(['before', 'route', 'after:body'], Trace::$log);
    }

    public function test_a_middleware_that_never_calls_next_stops_the_route(): void
    {
        [$router, , $response] = $this->router('GET', '/silent');
        $router->get('/silent', function () {
            Trace::$log[] = 'route';
            return 'body';
        }, [SilentMiddleware::class]);

        $this->assertSame($response, $router->resolve());
        $this->assertSame([], Trace::$log);
    }

    public function test_a_short_circuiting_middleware_can_return_its_own_body(): void
    {
        [$router] = $this->router('GET', '/cached');
        $router->get('/cached', fn() => 'fresh', [CachedBodyMiddleware::class]);

        $this->assertSame('from-cache', $router->resolve());
    }

    public function test_a_rejecting_guard_stops_inner_middlewares(): void
    {
        [$router] = $this->router('GET', '/guarded');
        $router->get('/guarded', fn() => 'ok', [BlockMiddleware::class, PassMiddleware::class]);
        $router->resolve();

        $this->assertNotContains('pass', Trace::$log);
    }

    public function test_route_literal_segments_are_not_regex(): void
    {
        [$router] = $this->router('GET', '/files/reportXjson');
        $router->get('/files/{name}.json', fn($name) => "file:{$name}");

        $router->resolve();
        $this->assertSame(404, $this->routerStatus($router));

        [$router] = $this->router('GET', '/files/report.json');
        $router->get('/files/{name}.json', fn($name) => "file:{$name}");
        $this->assertSame('file:report', $router->resolve());
    }

    private function routerStatus(\Spartan\Router $router): int
    {
        $prop = new \ReflectionProperty($router, 'response');
        return $prop->getValue($router)->getStatusCode();
    }

    // ─── QueryBuilder column whitelist ───────────────────────────────────────

    /**
     * @dataProvider unsafeExpressions
     */
    public function test_unlisted_functions_and_broken_literals_are_rejected(string $expression): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/Unsafe column expression/');

        $this->table('t_users')->orderBy($expression)->get();
    }

    public static function unsafeExpressions(): array
    {
        return [
            'substring oracle' => ["instr(email,'a')"],
            'string compare'   => ["STRCMP(email,'m')"],
            'sleep'            => ['SLEEP(5)'],
            'unbalanced quote' => ["UPPER(name ' )"],
            'comment'          => ['MAX(id --)'],
        ];
    }

    public function test_listed_functions_still_compile(): void
    {
        $row = $this->table('t_users')
            ->select('COUNT(*) as n', "COALESCE(role, 'none') as r", 'LOWER(name) as lname')
            ->where('id', 1)
            ->first();

        $this->assertSame(1, (int) $row['n']);
        $this->assertSame('admin', $row['r']);
        $this->assertSame('ada', $row['lname']);
    }

    // ─── Request: trusted proxies ────────────────────────────────────────────

    public function test_forged_left_most_forwarded_for_is_ignored(): void
    {
        Request::setTrustedProxies(['10.0.0.1']);
        $request = $this->request('GET', '/', [], [
            'REMOTE_ADDR'          => '10.0.0.1',
            'HTTP_X_FORWARDED_FOR' => '1.2.3.4, 203.0.113.9',
        ]);

        $this->assertSame('203.0.113.9', $request->getIp());
    }

    public function test_chained_trusted_proxies_are_skipped(): void
    {
        Request::setTrustedProxies(['10.0.0.0/8']);
        $request = $this->request('GET', '/', [], [
            'REMOTE_ADDR'          => '10.0.0.1',
            'HTTP_X_FORWARDED_FOR' => '203.0.113.9, 10.0.0.7',
        ]);

        $this->assertSame('203.0.113.9', $request->getIp());
    }

    public function test_forwarded_proto_is_only_trusted_from_a_proxy(): void
    {
        $untrusted = $this->request('GET', '/', [], ['REMOTE_ADDR' => '203.0.113.50', 'HTTP_X_FORWARDED_PROTO' => 'https']);
        $this->assertFalse($untrusted->isSecure());

        Request::setTrustedProxies(['10.0.0.1']);
        $trusted = $this->request('GET', '/', [], ['REMOTE_ADDR' => '10.0.0.1', 'HTTP_X_FORWARDED_PROTO' => 'https']);
        $this->assertTrue($trusted->isSecure());
    }

    // ─── Cache ───────────────────────────────────────────────────────────────

    public function test_has_does_not_make_a_later_get_return_stale_data(): void
    {
        Cache::put('stale', 'v1', 60);
        Cache::has('stale');

        // Another process rewrites the entry.
        file_put_contents($this->work . '/cache/' . md5('stale') . '.cache', serialize(['expires' => 0, 'value' => 'v2']));

        $this->assertSame('v2', Cache::get('stale'));
        $this->cacheKeyForget('stale');
    }

    public function test_remember_serves_cached_falsy_values_without_recomputing(): void
    {
        $calls = 0;
        $compute = function () use (&$calls) {
            $calls++;
            return null;
        };

        $this->assertNull(Cache::remember('nullish', 60, $compute));
        $this->assertNull(Cache::remember('nullish', 60, $compute));
        $this->assertSame(1, $calls);
        $this->cacheKeyForget('nullish');
    }

    public function test_remember_recomputes_an_expired_entry(): void
    {
        file_put_contents($this->work . '/cache/' . md5('expired') . '.cache', serialize(['expires' => time() - 5, 'value' => 'old']));

        $this->assertSame('new', Cache::remember('expired', 60, fn() => 'new'));
        $this->cacheKeyForget('expired');
    }

    // ─── Forms: old input, passwords, exit-free failures ─────────────────────

    public function test_old_reads_the_input_flashed_by_form_requests(): void
    {
        $this->app->session->setFlash('old_input', ['email' => 'kept@example.com']);

        $this->assertSame('kept@example.com', old('email'));
        $this->app->session->remove('flash_messages');
    }

    public function test_failed_validation_never_flashes_passwords(): void
    {
        $this->request('POST', '/', ['email' => 'nope', 'password' => 'secret', 'password_confirmation' => 'secret']);

        try {
            (new EmailRequest())->validate();
            $this->fail('validation should fail');
        } catch (\RuntimeException) {
        }

        $this->assertSame(['email' => 'nope'], $this->app->session->getFlash('old_input'));
        $this->app->session->remove('flash_messages');
    }

    public function test_a_prepared_response_exception_is_sent_instead_of_a_500(): void
    {
        $this->request('GET', '/early-exit');
        $this->app->router->setRequest(new Request());
        $this->app->router->setResponse($this->app->response);
        $this->app->router->get('/early-exit', function () {
            $response = $this->app->response;
            $response->setStatusCode(422);
            $response->setContent('stopped-early');
            throw new HttpResponseException($response);
        });

        ob_start();
        $this->app->run();
        $output = (string) ob_get_clean();

        $this->assertSame('stopped-early', $output);
        $this->assertSame(422, $this->app->response->getStatusCode());
    }

    // ─── Validator ───────────────────────────────────────────────────────────

    public function test_an_unknown_rule_is_a_developer_error(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new Validator())->validate(['name' => ''], ['name' => 'requird']);
    }

    public function test_array_rules_allow_a_pipe_inside_regex(): void
    {
        $validator = new Validator();

        $this->assertTrue($validator->validate(['c' => 'b'], ['c' => ['required', 'regex:/^(a|b)$/']]));
        $this->assertFalse($validator->validate(['c' => 'z'], ['c' => ['required', 'regex:/^(a|b)$/']]));
    }

    public function test_unique_can_ignore_the_record_being_updated(): void
    {
        $validator = new Validator();
        $validator->setDb($this->app->db);

        $this->assertFalse($validator->validate(['email' => 'ada@example.com'], ['email' => 'unique:t_users,email']));
        $this->assertTrue($validator->validate(['email' => 'ada@example.com'], ['email' => 'unique:t_users,email,1']));
        $this->assertFalse($validator->validate(['email' => 'ada@example.com'], ['email' => 'unique:t_users,email,2']));
    }

    // ─── Blade ───────────────────────────────────────────────────────────────

    public function test_email_addresses_in_templates_are_plain_text(): void
    {
        $view = $this->bladeView('mail', 'Write to info@authority.com or sales@elsewhere.org @csrf');
        $html = $view->renderViewOnly('mail');

        $this->assertStringContainsString('info@authority.com or sales@elsewhere.org', $html);
        $this->assertStringContainsString('name="_csrf"', $html);
    }

    public function test_text_glued_to_a_directive_still_compiles(): void
    {
        $view = $this->bladeView('glued', '@if($on)yes@endif|@if($off)no@else-off@endif');

        $this->assertSame('yes|-off', $view->renderViewOnly('glued', ['on' => true, 'off' => false]));
    }

    public function test_inline_sections_compile(): void
    {
        $dir = $this->work . '/tpl-inline';
        @mkdir($dir, 0777, true);
        file_put_contents("{$dir}/layout.blade.php", '<title>@yield("title")</title>@yield("content")');
        file_put_contents("{$dir}/page.blade.php", "@extends('layout')\n@section('title', \$title ?? 'Dashboard')\n@section(\"content\")body@endsection");

        $view = new View($dir);

        $this->assertSame('<title>Dashboard</title>body', trim($view->render('page')));
        $this->assertSame('<title>Mine</title>body', trim($view->render('page', ['title' => 'Mine'])));
    }

    public function test_at_escapes_print_literally(): void
    {
        $view = $this->bladeView('esc', '@@if and @{{ vueValue }}');

        $this->assertSame('@if and {{ vueValue }}', $view->renderViewOnly('esc'));
    }

    public function test_a_throwing_template_leaves_no_output_buffers_open(): void
    {
        $view  = $this->bladeView('boom', '@section("content") partial @php throw new \RuntimeException("boom"); @endphp @endsection');
        $level = ob_get_level();

        try {
            $view->renderViewOnly('boom');
            $this->fail('template should throw');
        } catch (\RuntimeException) {
        }

        $this->assertSame($level, ob_get_level());
    }

    private function bladeView(string $name, string $source): View
    {
        $dir = $this->work . '/tpl-hardening';
        @mkdir($dir, 0777, true);
        file_put_contents("{$dir}/{$name}.blade.php", $source);
        // Fresh mtime per test run so a cached compile is never reused.
        touch("{$dir}/{$name}.blade.php", time() + 1);

        return new View($dir);
    }

    // ─── Worker mode ─────────────────────────────────────────────────────────

    public function test_worker_requests_never_inherit_another_session(): void
    {
        $script = <<<'PHP'
        <?php
        ob_start();
        require $argv[1] . '/vendor/autoload.php';
        ini_set('session.save_path', $argv[2]);
        $session = new Spartan\Session();          // request A (no cookie)
        $session->set('user_id', 42);
        $session->close();
        $_COOKIE = [];                              // request B: anonymous
        $session->start();
        $out = var_export($session->get('user_id'), true);
        ob_end_clean();
        echo $out;
        PHP;

        $file = $this->work . '/worker-session.php';
        @mkdir($this->work . '/sessions', 0777, true);
        file_put_contents($file, $script);

        $output = shell_exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($file) . ' '
            . escapeshellarg(dirname(__DIR__, 2)) . ' ' . escapeshellarg($this->work . '/sessions'));

        $this->assertSame('NULL', trim((string) $output));
    }

    public function test_each_worker_request_uses_its_own_locale(): void
    {
        $translator = Translator::getInstance();
        $original   = $this->app->session;

        // In-memory session: the next request's session says 'ar'.
        $this->app->session = new class implements \Spartan\SessionInterface {
            private array $data = ['locale' => 'ar'];
            public function set(string $key, mixed $value): void { $this->data[$key] = $value; }
            public function get(string $key, mixed $default = null): mixed { return $this->data[$key] ?? $default; }
            public function has(string $key): bool { return isset($this->data[$key]); }
            public function remove(string $key): void { unset($this->data[$key]); }
            public function setFlash(string $key, mixed $message): void {}
            public function getFlash(string $key, mixed $default = null): mixed { return $default; }
            public function removeFlashMessages(): void {}
            public function destroy(): void {}
            public function regenerate(): void {}
            public function close(): void {}
        };
        $this->request('GET', '/no-such-page');

        try {
            ob_start();
            $this->app->handleRequest(new Request());
            ob_end_clean();

            $this->assertSame('ar', $translator->getLocale());
        } finally {
            $this->app->session = $original;
            $translator->setLocale('en');
            $this->app->router->setResponse($this->app->response);
        }
    }

    // ─── Error handling ──────────────────────────────────────────────────────

    public function test_the_error_page_drops_headers_set_before_the_failure(): void
    {
        $response = new Response();
        $response->redirect('/somewhere');

        ob_start();
        (new ExceptionHandler())->handle(new \RuntimeException('x'), $this->request(), $response, ['app' => ['debug' => false]]);
        ob_end_clean();

        $this->assertSame(500, $response->getStatusCode());
        $this->assertArrayNotHasKey('Location', $response->getHeaders());
    }

    // ─── Queue ───────────────────────────────────────────────────────────────

    public function test_reclaiming_counts_as_an_attempt_and_eventually_fails(): void
    {
        $driver = new DatabaseQueueDriver($this->app->db);
        $driver->push('e', 'Missing\\Listener', [], 2);
        $id = (int) $this->app->db->query('SELECT MAX(id) FROM jobs')->fetchColumn();

        $stale = date('Y-m-d H:i:s', time() - 3600);
        $this->app->db->exec("UPDATE jobs SET status = 'processing', run_at = '{$stale}' WHERE id = {$id}");
        $driver->reclaimStale();
        $this->assertSame(['pending', 1], $this->jobState($id));

        $this->app->db->exec("UPDATE jobs SET status = 'processing', run_at = '{$stale}' WHERE id = {$id}");
        $driver->reclaimStale();
        $this->assertSame(['failed', 2], $this->jobState($id));
    }

    public function test_non_retry_failure_modes_fit_the_jobs_table_enum(): void
    {
        $driver = new DatabaseQueueDriver($this->app->db);
        $driver->push('e', 'L', [], 3, 'bury');

        $this->assertSame('stop', $this->app->db->query('SELECT on_failure FROM jobs ORDER BY id DESC LIMIT 1')->fetchColumn());
    }

    public function test_touch_refreshes_the_claim_of_waiting_jobs(): void
    {
        $driver = new DatabaseQueueDriver($this->app->db);
        $driver->push('e', 'L', []);
        $id = (int) $this->app->db->query('SELECT MAX(id) FROM jobs')->fetchColumn();
        $this->app->db->exec("UPDATE jobs SET status = 'processing', run_at = '2000-01-01 00:00:00' WHERE id = {$id}");

        $driver->touch([$id]);

        $this->assertSame(0, $driver->reclaimStale());
    }

    /** @return array{0:string,1:int} */
    private function jobState(int $id): array
    {
        $row = $this->app->db->query("SELECT status, attempts FROM jobs WHERE id = {$id}")->fetch(\PDO::FETCH_ASSOC);
        return [$row['status'], (int) $row['attempts']];
    }

    // ─── Misc ────────────────────────────────────────────────────────────────

    public function test_executable_upload_extensions_are_neutralised(): void
    {
        $file = new UploadedFile(['name' => 'shell.PHP', 'tmp_name' => '', 'error' => UPLOAD_ERR_OK, 'size' => 1]);
        $this->assertStringEndsWith('.bin', $file->hashName());

        $image = new UploadedFile(['name' => 'photo.JPG', 'tmp_name' => '', 'error' => UPLOAD_ERR_OK, 'size' => 1]);
        $this->assertStringEndsWith('.jpg', $image->hashName());
    }

    public function test_json_log_entries_survive_invalid_utf8(): void
    {
        $dir    = $this->work . '/logs-utf8';
        $logger = new Logger($dir, 'json');
        $logger->info("bad \xB1 byte", ['input' => "\xFF"]);

        $line = file_get_contents($dir . '/app-' . date('Y-m-d') . '.log');
        $this->assertStringContainsString('"level":"INFO"', (string) $line);
    }

    public function test_models_work_with_an_auth_implementation_without_forget_user(): void
    {
        $original = $this->app->auth;
        $this->app->auth = new class implements AuthInterface {
            public function user(bool $fresh = false): ?object { return null; }
            public function id(): int|string|null { return null; }
            public function check(): bool { return false; }
        };

        try {
            $id = (new UserModel())->create(['name' => 'Custom', 'email' => 'c@example.com']);
            $this->assertSame(1, (new UserModel())->save($id, ['name' => 'Custom2']));
        } finally {
            $this->app->auth = $original;
        }
    }
}

final class WrappingMiddleware extends Middleware
{
    public function handle(Request $request, Response $response, callable $next): mixed
    {
        Trace::$log[] = 'before';
        $result = $next();
        Trace::$log[] = 'after:' . $result;
        return '[' . $result . ']';
    }
}

final class SilentMiddleware extends Middleware
{
    public function handle(Request $request, Response $response, callable $next): mixed
    {
        return null;
    }
}

final class CachedBodyMiddleware extends Middleware
{
    public function handle(Request $request, Response $response, callable $next): mixed
    {
        return 'from-cache';
    }
}
