<?php

declare(strict_types=1);

namespace Spartan\Tests\Unit;

use Spartan\Response;
use Spartan\Tests\Fixtures\PlainController;
use Spartan\Tests\TestCase;

final class RouterTest extends TestCase
{
    /**
     * @dataProvider verbs
     */
    public function test_every_verb_routes(string $verb): void
    {
        [$router] = $this->router($verb, '/thing');
        $router->{strtolower($verb)}('/thing', fn() => $verb . '-ok');

        $this->assertSame($verb . '-ok', $router->resolve());
    }

    public static function verbs(): array
    {
        return [['GET'], ['POST'], ['PUT'], ['PATCH'], ['DELETE']];
    }

    public function test_injects_a_dynamic_parameter(): void
    {
        [$router] = $this->router('GET', '/users/42');
        $router->get('/users/{id}', fn($id) => "id={$id}");

        $this->assertSame('id=42', $router->resolve());
    }

    public function test_injects_multiple_parameters_positionally(): void
    {
        [$router] = $this->router('GET', '/u/7/posts/9');
        $router->get('/u/{user}/posts/{post}', fn($a, $b) => "{$a}:{$b}");

        $this->assertSame('7:9', $router->resolve(), 'closure params need not match placeholder names');
    }

    public function test_injects_parameters_by_name_when_they_match(): void
    {
        [$router] = $this->router('GET', '/u/7/posts/9');
        $router->get('/u/{user}/posts/{post}', fn($post, $user) => "{$user}:{$post}");

        $this->assertSame('7:9', $router->resolve(), 'named binding survives reordered parameters');
    }

    public function test_parameters_do_not_span_path_segments(): void
    {
        [$router, , $response] = $this->router('GET', '/users/1/extra');
        $router->get('/users/{id}', fn($id) => 'matched');
        $router->resolve();

        $this->assertSame(404, $response->getStatusCode());
    }

    public function test_static_route_wins_over_a_dynamic_one(): void
    {
        [$router] = $this->router('GET', '/users/me');
        $router->get('/users/{id}', fn($id) => 'dynamic');
        $router->get('/users/me', fn() => 'static');

        $this->assertSame('static', $router->resolve());
    }

    public function test_unmatched_path_is_404(): void
    {
        [$router, , $response] = $this->router('GET', '/no/such/route');
        $router->get('/exists', fn() => 'x');
        $body = $router->resolve();

        $this->assertSame(404, $response->getStatusCode());
        $this->assertIsString($body);
    }

    public function test_known_path_with_the_wrong_verb_is_404(): void
    {
        [$router, , $response] = $this->router('POST', '/only-get');
        $router->get('/only-get', fn() => 'x');
        $router->resolve();

        $this->assertSame(404, $response->getStatusCode());
    }

    public function test_dispatches_to_a_controller_action(): void
    {
        [$router] = $this->router('GET', '/ctrl');
        $router->get('/ctrl', [PlainController::class, 'plain']);

        $this->assertSame('plain-ok', $router->resolve());
    }

    public function test_injects_the_request_into_an_action(): void
    {
        [$router] = $this->router('GET', '/inject');
        $router->get('/inject', [PlainController::class, 'withRequest']);

        $this->assertSame('path=/inject', $router->resolve());
    }

    public function test_injects_and_validates_a_form_request(): void
    {
        [$router] = $this->router('POST', '/form');
        $_POST = ['email' => 'user@example.com'];
        $router->post('/form', [PlainController::class, 'withFormRequest']);

        $this->assertSame('valid:user@example.com', $router->resolve());
    }

    public function test_mixes_request_injection_with_route_parameters(): void
    {
        [$router] = $this->router('GET', '/mix/5');
        $router->get('/mix/{id}', [PlainController::class, 'mixed']);

        $this->assertSame('req+5', $router->resolve());
    }

    public function test_applies_default_parameter_values(): void
    {
        [$router] = $this->router('GET', '/withdefault');
        $router->get('/withdefault', [PlainController::class, 'withDefault']);

        $this->assertSame('page=1', $router->resolve());
    }

    public function test_missing_required_parameter_raises_a_descriptive_error(): void
    {
        [$router] = $this->router('GET', '/noparam');
        $router->get('/noparam', [PlainController::class, 'needsParam']);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/was not supplied/');
        $router->resolve();
    }

    public function test_unknown_controller_class_is_rejected(): void
    {
        [$router] = $this->router('GET', '/ghost');
        $router->get('/ghost', ['No\\Such\\Controller', 'index']);

        $this->expectException(\InvalidArgumentException::class);
        $router->resolve();
    }

    public function test_unknown_action_raises_bad_method_call(): void
    {
        [$router] = $this->router('GET', '/badaction');
        $router->get('/badaction', [PlainController::class, 'nope']);

        $this->expectException(\BadMethodCallException::class);
        $router->resolve();
    }

    public function test_csrf_exclusions_are_recorded(): void
    {
        [$router] = $this->router();
        $router->excludeCsrf('/api/*', '/webhooks/*');

        $this->assertSame(['/api/*', '/webhooks/*'], $router->getCsrfExclusions());
    }

    public function test_route_cache_round_trip(): void
    {
        $file = $this->work . '/routes-test.php';
        $this->app->config['router'] = ['cache_enabled' => true, 'cache_file' => $file];

        [$writer] = $this->router('GET', '/cached/9');
        $writer->get('/cached/{id}', [PlainController::class, 'plain']);

        $this->assertTrue($writer->saveCache());
        $this->assertFileExists($file);

        [$reader] = $this->router('GET', '/cached/9');
        $this->assertTrue($reader->loadCache());
        $this->assertSame('plain-ok', $reader->resolve());

        $this->app->config['router'] = ['cache_enabled' => false, 'cache_file' => null];
    }

    public function test_closure_routes_cannot_be_cached(): void
    {
        $this->app->config['router'] = ['cache_enabled' => true, 'cache_file' => $this->work . '/routes-closure.php'];

        [$router] = $this->router();
        $router->get('/closure', fn() => 'x');

        $this->expectException(\LogicException::class);
        try {
            $router->saveCache();
        } finally {
            $this->app->config['router'] = ['cache_enabled' => false, 'cache_file' => null];
        }
    }

    public function test_route_cache_is_written_atomically(): void
    {
        $source = file_get_contents(__DIR__ . '/../../framework/src/Router.php');

        $this->assertStringContainsString('rename($tmp, $file)', $source);
    }
}
