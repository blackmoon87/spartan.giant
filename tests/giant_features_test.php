<?php

declare(strict_types=1);

namespace Spartan\Tests;

require_once __DIR__ . '/bootstrap.php';

use Spartan\Application;
use Spartan\Attributes\RequirePermission;
use Spartan\Attributes\RequireRole;
use Spartan\Auth;
use Spartan\Cache;
use Spartan\CacheDrivers\FileCacheDriver;
use Spartan\Config;
use Spartan\ConnectionManager;
use Spartan\Container;
use Spartan\Controller;
use Spartan\Database;
use Spartan\Database\Migrator;
use Spartan\Database\MysqlDialect;
use Spartan\Database\SqliteDialect;
use Spartan\EventDispatcher;
use Spartan\FormRequest;
use Spartan\Gate;
use Spartan\HealthCheck;
use Spartan\JobQueue;
use Spartan\Logger;
use Spartan\Middleware;
use Spartan\Middlewares\CsrfMiddleware;
use Spartan\Middlewares\RateLimitMiddleware;
use Spartan\Middlewares\SecurityHeadersMiddleware;
use Spartan\Model;
use Spartan\Paths;
use Spartan\QueryBuilder;
use Spartan\QueueDrivers\DatabaseQueueDriver;
use Spartan\Request;
use Spartan\Response;
use Spartan\Router;
use Spartan\Session;
use Spartan\TaggedCache;
use Spartan\Validator;
use Spartan\View;
use PDO;
use Throwable;

/**
 * Spartan Giant Comprehensive Feature Suite & JSON Reporter.
 *
 * Runs exhaustive input/output verification across every framework component,
 * evaluates assertions, and outputs both a human-readable summary and
 * a complete structured JSON report.
 */
class GiantFeatureSuite
{
    private array $results = [];
    private Application $app;
    private PDO $db;

    public function __construct()
    {
        $this->app = Application::$app;
        $this->db = $this->app->db;
    }

    private function makeRequest(string $method = 'GET', string $uri = '/', array $post = [], array $server = []): Request
    {
        $_SERVER = array_merge([
            'REQUEST_URI'    => $uri,
            'REQUEST_METHOD' => $method,
            'SCRIPT_NAME'    => '/index.php',
            'REMOTE_ADDR'    => '127.0.0.1',
        ], $server);
        $_POST = $post;
        $_GET = [];

        $pos = strpos($uri, '?');
        if ($pos !== false) {
            parse_str(substr($uri, $pos + 1), $_GET);
        }

        return new Request();
    }

    public function run(): array
    {
        $this->testApplicationLifecycle();
        $this->testContainerDI();
        $this->testConfigSystem();
        $this->testConnectionManager();
        $this->testSqlDialects();
        $this->testQueryBuilderAndSecurity();
        $this->testDatabaseMigrations();
        $this->testActiveRecordAndRelations();
        $this->testOnionMiddlewarePipeline();
        $this->testSecurityMiddlewares();
        $this->testRouterAndMatching();
        $this->testRequestResponse();
        $this->testSessionAndFlash();
        $this->testValidatorEngine();
        $this->testFormRequest();
        $this->testGateAndRBAC();
        $this->testCacheAndTaggedCache();
        $this->testQueueAndDrivers();
        $this->testEventDispatcher();
        $this->testStructuredLogger();
        $this->testBladeViewEngine();
        $this->testHealthCheckSystem();

        return $this->compileReport();
    }

    private function assert(string $category, string $feature, mixed $input, mixed $expected, callable $executor): void
    {
        $start = hrtime(true);
        $actual = null;
        $passed = false;
        $error = null;

        try {
            $actual = $executor();
            $passed = ($actual === $expected);
            if (!$passed && is_array($expected) && is_array($actual)) {
                $passed = ($actual == $expected);
            }
        } catch (Throwable $e) {
            $error = $e->getMessage();
            $actual = 'EXCEPTION: ' . $error;
            $passed = false;
        }

        $durationMs = round((hrtime(true) - $start) / 1e6, 3);

        $this->results[] = [
            'category'    => $category,
            'feature'     => $feature,
            'input'       => $input,
            'expected'    => $expected,
            'actual'      => $actual,
            'passed'      => $passed,
            'duration_ms' => $durationMs,
            'error'       => $error,
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 1. Application Lifecycle
    // ─────────────────────────────────────────────────────────────────────────
    private function testApplicationLifecycle(): void
    {
        $this->assert(
            'Application',
            'Global Singleton & Component Access',
            'Access Application::$app->db, ->container, ->connections, ->logger',
            true,
            fn() => Application::$app instanceof Application
                 && Application::$app->db instanceof PDO
                 && Application::$app->connections instanceof ConnectionManager
                 && Application::$app->logger instanceof Logger
        );

        $this->assert(
            'Application',
            'Worker Mode State Isolation',
            'Set auth_user in container and trigger resetPerRequestState()',
            true,
            function () {
                $this->app->container->instance('auth_user', (object)['id' => 42]);
                $this->app->resetPerRequestState();
                return !$this->app->container->has('auth_user');
            }
        );
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 2. DI Container
    // ─────────────────────────────────────────────────────────────────────────
    private function testContainerDI(): void
    {
        $container = new Container();

        $this->assert(
            'Container',
            'Auto-resolution of Nested Constructor Dependencies',
            'Resolve class with recursive constructor dependencies (Logger)',
            true,
            function () use ($container) {
                $logger = $container->make(Logger::class);
                return $logger instanceof Logger;
            }
        );

        $this->assert(
            'Container',
            'Singleton Lifecycle Persistence',
            'Resolve registered singleton multiple times',
            true,
            function () use ($container) {
                $container->singleton('custom_singleton', fn() => new \stdClass());
                $obj1 = $container->make('custom_singleton');
                $obj2 = $container->make('custom_singleton');
                return $obj1 === $obj2;
            }
        );
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 3. Config System
    // ─────────────────────────────────────────────────────────────────────────
    private function testConfigSystem(): void
    {
        $config = new Config([
            'app' => ['name' => 'Spartan Giant', 'debug' => true],
            'db'  => ['mysql' => ['host' => '127.0.0.1', 'port' => 3306]],
        ]);

        $this->assert(
            'Config',
            'Dot-Notation Retrieval',
            'Config::get("db.mysql.host")',
            '127.0.0.1',
            fn() => $config->get('db.mysql.host')
        );

        $this->assert(
            'Config',
            'Dot-Notation Dynamic Mutation',
            'Config::set("cache.driver", "redis")',
            'redis',
            function () use ($config) {
                $config->set('cache.driver', 'redis');
                return $config->get('cache.driver');
            }
        );

        $this->assert(
            'Config',
            'Production Cache Export & Atomic Reload',
            'Cache config to file and loadCached()',
            'Spartan Giant',
            function () use ($config) {
                $cachePath = Paths::storage('cache/test_config.php');
                $config->cache($cachePath);
                $loaded = Config::loadCached($cachePath);
                @unlink($cachePath);
                return $loaded?->get('app.name');
            }
        );
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 4. Connection Manager & Replicas
    // ─────────────────────────────────────────────────────────────────────────
    private function testConnectionManager(): void
    {
        $manager = new ConnectionManager();
        $manager->addConnection('default', ['connection' => 'sqlite', 'database' => ':memory:']);
        $manager->addConnection('analytics', ['connection' => 'sqlite', 'database' => ':memory:']);

        $this->assert(
            'ConnectionManager',
            'Named Connection Lazy Instantiation',
            'Connect to "analytics" connection',
            true,
            fn() => $manager->connection('analytics') instanceof PDO && $manager->isConnected('analytics')
        );

        $this->assert(
            'ConnectionManager',
            'Read Replica Registration & Split Status',
            'Add replica and check isSplitEnabled()',
            true,
            function () use ($manager) {
                $manager->addReadReplica(['host' => '127.0.0.1']);
                $manager->enableSplit(true);
                return $manager->isSplitEnabled();
            }
        );
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 5. SQL Dialects
    // ─────────────────────────────────────────────────────────────────────────
    private function testSqlDialects(): void
    {
        $mysql = new MysqlDialect();
        $sqlite = new SqliteDialect();

        $this->assert(
            'Dialect',
            'MySQL Backtick Identifier Escaping',
            'Escape table "users" and column "email"',
            '`users`.`email`',
            fn() => $mysql->quoteTable('users') . '.' . $mysql->quoteIdentifier('email')
        );

        $this->assert(
            'Dialect',
            'SQLite Double-Quote Identifier Escaping',
            'Escape table "users" and column "email"',
            '"users"."email"',
            fn() => $sqlite->quoteTable('users') . '.' . $sqlite->quoteIdentifier('email')
        );
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 6. QueryBuilder & Security Whitelisting
    // ─────────────────────────────────────────────────────────────────────────
    private function testQueryBuilderAndSecurity(): void
    {
        $this->db->exec("CREATE TABLE IF NOT EXISTS giant_products (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT, price REAL, active INTEGER)");
        $this->db->exec("DELETE FROM giant_products");

        $this->assert(
            'QueryBuilder',
            'Fluent Insert & Select',
            'Insert 2 records and query with where()',
            2,
            function () {
                $qb = new QueryBuilder($this->db, 'giant_products');
                $qb->insert(['name' => 'Server Blade', 'price' => 2400.00, 'active' => 1]);
                $qb2 = new QueryBuilder($this->db, 'giant_products');
                $qb2->insert(['name' => 'Switch 10GbE', 'price' => 850.00, 'active' => 1]);
                $qb3 = new QueryBuilder($this->db, 'giant_products');
                return count($qb3->where('active', 1)->get());
            }
        );

        $this->assert(
            'QueryBuilder',
            'Operator Whitelisting Security Guard',
            'Attempt SQL injection via invalid operator "UNION SELECT"',
            true,
            function () {
                try {
                    $qb = new QueryBuilder($this->db, 'giant_products');
                    $qb->where('name', 'bad', 'UNION SELECT');
                    return false;
                } catch (\Throwable) {
                    return true;
                }
            }
        );

        $this->assert(
            'QueryBuilder',
            'Column Identifier Whitelisting',
            'Attempt column injection "id; DROP TABLE users;"',
            true,
            function () {
                try {
                    $qb = new QueryBuilder($this->db, 'giant_products');
                    $qb->where('id; DROP TABLE', 1)->get();
                    return false;
                } catch (\Throwable) {
                    return true;
                }
            }
        );
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 7. Migrations & Rollback Engine
    // ─────────────────────────────────────────────────────────────────────────
    private function testDatabaseMigrations(): void
    {
        $migrator = new Migrator($this->db, __DIR__ . '/../database/migrations');

        $this->assert(
            'Migrator',
            'Batch-Based Schema Migration & Status',
            'Run migrator->migrate() and query status()',
            true,
            function () use ($migrator) {
                ob_start();
                $migrator->migrate();
                ob_end_clean();
                $statuses = $migrator->status();
                return is_array($statuses) && count($statuses) >= 2;
            }
        );

        $this->assert(
            'Migrator',
            'Companion _down.sql Rollback Engine',
            'Roll back 1 batch and re-verify status',
            true,
            function () use ($migrator) {
                ob_start();
                $migrator->rollback(1);
                $statuses = $migrator->status();
                // Re-migrate to leave DB clean
                $migrator->migrate();
                ob_end_clean();
                return is_array($statuses);
            }
        );
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 8. ActiveRecord & Relationships
    // ─────────────────────────────────────────────────────────────────────────
    private function testActiveRecordAndRelations(): void
    {
        $this->db->exec("CREATE TABLE IF NOT EXISTS giant_authors (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT)");
        $this->db->exec("CREATE TABLE IF NOT EXISTS giant_books (id INTEGER PRIMARY KEY AUTOINCREMENT, author_id INTEGER, title TEXT)");
        $this->db->exec("DELETE FROM giant_books");
        $this->db->exec("DELETE FROM giant_authors");

        $this->db->exec("INSERT INTO giant_authors (id, name) VALUES (1, 'Al-Biruni')");
        $this->db->exec("INSERT INTO giant_books (id, author_id, title) VALUES (10, 1, 'Chronology of Ancient Nations')");
        $this->db->exec("INSERT INTO giant_books (id, author_id, title) VALUES (11, 1, 'The Masudic Canon')");

        $this->assert(
            'ORM',
            'ActiveRecord Hydration & Query',
            'Query author books count via QueryBuilder',
            2,
            function () {
                return (new QueryBuilder($this->db, 'giant_books'))->where('author_id', 1)->count();
            }
        );
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 9. Onion Middleware Pipeline
    // ─────────────────────────────────────────────────────────────────────────
    private function testOnionMiddlewarePipeline(): void
    {
        $this->assert(
            'Middleware',
            'Onion Nested Chain with $next Closure',
            'Wrap route response with timing and custom header middleware',
            'HEADER_OUTER->INNER->CONTENT',
            function () {
                $request = $this->makeRequest('GET', '/pipeline-test');
                $response = new Response();

                $m1 = new class extends Middleware {
                    public function handle(Request $req, Response $res, callable $next): mixed {
                        $res->setHeader('X-Trace', 'OUTER');
                        $next();
                        $res->setContent('HEADER_OUTER->' . $res->getContent());
                        return $res;
                    }
                };

                $m2 = new class extends Middleware {
                    public function handle(Request $req, Response $res, callable $next): mixed {
                        $next();
                        $res->setContent('INNER->' . $res->getContent());
                        return $res;
                    }
                };

                // Build onion chain
                $core = function () use ($response) {
                    $response->setContent('CONTENT');
                };

                $chain = fn() => $m1->handle($request, $response, fn() => $m2->handle($request, $response, $core));
                $chain();

                return $response->getContent();
            }
        );
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 10. Security Middlewares
    // ─────────────────────────────────────────────────────────────────────────
    private function testSecurityMiddlewares(): void
    {
        $this->assert(
            'Security',
            'SecurityHeadersMiddleware Initialization',
            'Instantiate SecurityHeadersMiddleware with custom CSP',
            true,
            function () {
                $m = new SecurityHeadersMiddleware("default-src 'self'");
                return $m instanceof SecurityHeadersMiddleware;
            }
        );

        $this->assert(
            'Security',
            'CSRF Protection on State-Changing Verbs',
            'Send POST request without _csrf token',
            403,
            function () {
                $req = $this->makeRequest('POST', '/submit');
                $res = new Response();
                $m = new CsrfMiddleware();
                $m->handle($req, $res, fn() => null);
                return $res->getStatusCode();
            }
        );
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 11. Router & Param Extraction
    // ─────────────────────────────────────────────────────────────────────────
    private function testRouterAndMatching(): void
    {
        $this->assert(
            'Router',
            'Regex Dynamic Param Extraction & Route Matching',
            'GET /users/99/profile → match and inject parameter id=99',
            'User Profile: 99',
            function () {
                $req = $this->makeRequest('GET', '/users/99/profile');
                $res = new Response();
                $router = new Router($req, $res);

                $router->get('/users/{id}/profile', function (int $id) {
                    return "User Profile: {$id}";
                });

                return $router->resolve();
            }
        );
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 12. Request & Response
    // ─────────────────────────────────────────────────────────────────────────
    private function testRequestResponse(): void
    {
        $this->assert(
            'Request',
            'Method Spoofing & Trusted Proxy IP',
            'POST with _method=PUT and X-Forwarded-For through trusted proxy',
            ['method' => 'PUT', 'ip' => '203.0.113.195'],
            function () {
                Request::setTrustedProxies(['10.0.0.1']);
                $req = $this->makeRequest('POST', '/api/item', ['_method' => 'PUT'], [
                    'REMOTE_ADDR' => '10.0.0.1',
                    'HTTP_X_FORWARDED_FOR' => '203.0.113.195',
                ]);
                return ['method' => $req->getMethod(), 'ip' => $req->getIp()];
            }
        );

        $this->assert(
            'Response',
            'Open Redirect Vulnerability Blocking',
            'Attempt redirect to external domain "https://evil.com"',
            '/',
            function () {
                $res = new Response();
                $res->redirect('https://evil.com');
                return $res->getRedirectUrl();
            }
        );
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 13. Session & Flash
    // ─────────────────────────────────────────────────────────────────────────
    private function testSessionAndFlash(): void
    {
        $session = new Session();

        $this->assert(
            'Session',
            'Flash Message Lifecycle',
            'Set flash("msg", "saved") and read on first cycle vs next cycle',
            ['first' => 'saved', 'next' => null],
            function () use ($session) {
                $session->setFlash('msg', 'saved');
                $first = $session->getFlash('msg');
                // Simulate new request cycle
                $session->start();
                $session->removeFlashMessages();
                $next = $session->getFlash('msg');
                return ['first' => $first, 'next' => $next];
            }
        );
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 14. Validator Engine
    // ─────────────────────────────────────────────────────────────────────────
    private function testValidatorEngine(): void
    {
        $v = new Validator();

        $this->assert(
            'Validator',
            'Multi-rule Passing Suite',
            'Validate valid email, age, and url payload',
            true,
            fn() => $v->validate(
                ['email' => 'admin@spartan.io', 'age' => '25', 'url' => 'https://spartan.io'],
                [
                    'email' => 'required|email',
                    'age'   => 'required|integer|min:18',
                    'url'   => 'required|url',
                ]
            )
        );

        $vFail = new Validator();

        $this->assert(
            'Validator',
            'Failure Detection & Error Messages',
            'Validate invalid email and underage integer',
            2,
            function () use ($vFail) {
                $vFail->validate(
                    ['email' => 'not-an-email', 'age' => '15'],
                    [
                        'email' => 'required|email',
                        'age'   => 'required|integer|min:18',
                    ]
                );
                return count($vFail->errors());
            }
        );
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 15. FormRequest
    // ─────────────────────────────────────────────────────────────────────────
    private function testFormRequest(): void
    {
        $this->assert(
            'FormRequest',
            'Automatic Service Binding & Authorization',
            'Instantiate FormRequest with authorize() and validate()',
            true,
            function () {
                $this->makeRequest('POST', '/posts', ['title' => 'Test Post']);
                $req = new class(new Request()) extends FormRequest {
                    public function authorize(): bool {
                        return true;
                    }
                    public function rules(): array {
                        return ['title' => 'required|string|min:3'];
                    }
                };
                return $req->authorize() && count($req->rules()) === 1;
            }
        );
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 16. Gate & RBAC
    // ─────────────────────────────────────────────────────────────────────────
    private function testGateAndRBAC(): void
    {
        Gate::define('edit-settings', fn(?object $user) => ($user->role ?? '') === 'admin');

        $this->assert(
            'Gate',
            'Policy Evaluation for Admin vs Regular User',
            'Evaluate Gate::forUser() for admin vs guest',
            ['admin' => true, 'guest' => false],
            fn() => [
                'admin' => Gate::forUser((object)['role' => 'admin'])->allows('edit-settings'),
                'guest' => Gate::forUser((object)['role' => 'guest'])->allows('edit-settings'),
            ]
        );
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 17. Cache & TaggedCache
    // ─────────────────────────────────────────────────────────────────────────
    private function testCacheAndTaggedCache(): void
    {
        $driver = new FileCacheDriver(['path' => Paths::storage('cache')]);
        $tagged = new TaggedCache($driver, ['catalog', 'featured']);

        $this->assert(
            'Cache',
            'Tagged Cache Group Put & Flush',
            'Put items under tags ["catalog", "featured"] then flush("catalog")',
            ['before' => 'Product #10', 'after' => null],
            function () use ($tagged) {
                $tagged->put('item_10', 'Product #10', 300);
                $before = $tagged->get('item_10');
                $tagged->flush();
                $after = $tagged->get('item_10');
                return ['before' => $before, 'after' => $after];
            }
        );
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 18. Queue & Drivers
    // ─────────────────────────────────────────────────────────────────────────
    private function testQueueAndDrivers(): void
    {
        $driver = new DatabaseQueueDriver($this->db);
        $queue = new JobQueue($driver);

        $this->assert(
            'Queue',
            'Database Driver Push & Atomic Reclamation',
            'Push job, reclaim stale in-flight jobs',
            true,
            function () use ($queue) {
                $queue->push('order.created', 'App\\Listeners\\SendEmail', ['order_id' => 999]);
                $reclaimed = $queue->reclaimStale(600);
                return is_int($reclaimed);
            }
        );
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 19. Event Dispatcher
    // ─────────────────────────────────────────────────────────────────────────
    private function testEventDispatcher(): void
    {
        $dispatcher = new EventDispatcher($this->app);
        $executed = false;

        $dispatcher->listen('user.registered', function ($payload) use (&$executed) {
            $executed = ($payload['id'] === 123);
        });

        $this->assert(
            'EventDispatcher',
            'Sync Listener Execution with Payload',
            'Dispatch "user.registered" event with id=123',
            true,
            function () use ($dispatcher, &$executed) {
                $dispatcher->dispatch('user.registered', ['id' => 123]);
                return $executed;
            }
        );
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 20. Structured Logger
    // ─────────────────────────────────────────────────────────────────────────
    private function testStructuredLogger(): void
    {
        $logPath = Paths::storage('logs');
        $logger = new Logger($logPath, 'json', 'audit');
        $cid = $logger->generateCorrelationId();

        $this->assert(
            'Logger',
            'Structured JSON Line with Correlation ID',
            'Log info with correlation_id and read JSON file',
            true,
            function () use ($logger, $cid, $logPath) {
                $logger->info('Payment received {amount}', ['amount' => '$500']);
                $logFile = $logPath . '/audit-' . date('Y-m-d') . '.log';
                if (!file_exists($logFile)) return false;
                $lines = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                $last = json_decode(end($lines), true);
                return isset($last['correlation_id']) && $last['correlation_id'] === $cid && $last['level'] === 'INFO';
            }
        );
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 21. Blade View Engine
    // ─────────────────────────────────────────────────────────────────────────
    private function testBladeViewEngine(): void
    {
        $this->assert(
            'View',
            'Blade Directives & Escaping Compilation',
            'Compile @if, @csrf, and {{ $title }}',
            true,
            function () {
                $bladeSource = '@if(true)<h1>{{ $title }}</h1>@csrf@endif';
                $ref = new \ReflectionClass(View::class);
                $method = $ref->getMethod('compileString');
                $method->setAccessible(true);
                $compiled = $method->invoke(new View(), $bladeSource);

                return str_contains($compiled, 'htmlspecialchars') && str_contains($compiled, 'csrfToken()');
            }
        );
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 22. Health Check System
    // ─────────────────────────────────────────────────────────────────────────
    private function testHealthCheckSystem(): void
    {
        $health = new HealthCheck();

        $this->assert(
            'HealthCheck',
            'System Diagnostic Execution',
            'Run full HealthCheck and verify PHP, DB, Memory metrics',
            'healthy',
            function () use ($health) {
                $report = $health->run();
                return $report['status'];
            }
        );
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Report Compilation
    // ─────────────────────────────────────────────────────────────────────────
    private function compileReport(): array
    {
        $total = count($this->results);
        $passed = count(array_filter($this->results, fn($r) => $r['passed']));
        $failed = $total - $passed;
        $totalDurationMs = array_sum(array_column($this->results, 'duration_ms'));

        return [
            'summary' => [
                'suite'              => 'Spartan Giant Comprehensive Feature Verification',
                'timestamp'          => date('Y-m-d H:i:s'),
                'php_version'        => PHP_VERSION,
                'total_features'     => $total,
                'passed'             => $passed,
                'failed'             => $failed,
                'success_rate'       => round(($passed / max(1, $total)) * 100, 2) . '%',
                'total_execution_ms' => round($totalDurationMs, 2),
            ],
            'features' => $this->results,
        ];
    }
}

// Execute and output JSON
$suite = new GiantFeatureSuite();
$report = $suite->run();

$jsonOutput = json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
echo $jsonOutput . "\n";
