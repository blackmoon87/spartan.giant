<?php

declare(strict_types=1);

namespace Spartan\Tests\Unit;

use Spartan\Middlewares\SecurityHeadersMiddleware;
use Spartan\Response;
use Spartan\Tests\Fixtures\ArgsMiddleware;
use Spartan\Tests\Fixtures\BlockMiddleware;
use Spartan\Tests\Fixtures\PassMiddleware;
use Spartan\Tests\Fixtures\SecondMiddleware;
use Spartan\Tests\Fixtures\Trace;
use Spartan\Tests\TestCase;

final class MiddlewareTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Trace::reset();
    }

    public function test_route_middleware_runs_before_the_action(): void
    {
        [$router] = $this->router('GET', '/mw');
        $router->get('/mw', function () {
            Trace::$log[] = 'action';
            return 'body';
        }, [PassMiddleware::class]);

        $this->assertSame('body', $router->resolve());
        $this->assertSame(['pass', 'action'], Trace::$log);
    }

    public function test_a_blocking_middleware_stops_the_action(): void
    {
        [$router, , $response] = $this->router('GET', '/blocked');
        $router->get('/blocked', function () {
            Trace::$log[] = 'action';
            return 'body';
        }, [BlockMiddleware::class]);

        $result = $router->resolve();

        $this->assertInstanceOf(Response::class, $result);
        $this->assertSame(403, $response->getStatusCode());
        $this->assertNotContains('action', Trace::$log);
    }

    public function test_global_middleware_runs_on_matched_routes(): void
    {
        [$router] = $this->router('GET', '/g');
        $router->setGlobalMiddlewares([PassMiddleware::class]);
        $router->get('/g', fn() => 'ok');
        $router->resolve();

        $this->assertSame(['pass'], Trace::$log);
    }

    public function test_global_middleware_runs_on_unmatched_routes(): void
    {
        [$router, , $response] = $this->router('GET', '/missing-page');
        $router->setGlobalMiddlewares([PassMiddleware::class]);
        $router->get('/other', fn() => 'ok');
        $router->resolve();

        $this->assertSame(['pass'], Trace::$log, 'security headers must still apply to 404 traffic');
        $this->assertSame(404, $response->getStatusCode());
    }

    public function test_global_middleware_precedes_route_middleware(): void
    {
        [$router] = $this->router('GET', '/both');
        $router->setGlobalMiddlewares([PassMiddleware::class]);
        $router->get('/both', fn() => 'ok', [SecondMiddleware::class]);
        $router->resolve();

        $this->assertSame(['pass', 'second'], Trace::$log);
    }

    public function test_middleware_groups_expand_in_order(): void
    {
        [$router] = $this->router('GET', '/grp');
        $router->middlewareGroup('web', [PassMiddleware::class, SecondMiddleware::class]);
        $router->get('/grp', fn() => 'ok', ['web']);
        $router->resolve();

        $this->assertSame(['pass', 'second'], Trace::$log);
    }

    public function test_nested_groups_expand(): void
    {
        [$router] = $this->router('GET', '/nested');
        $router->middlewareGroup('inner', [SecondMiddleware::class]);
        $router->middlewareGroup('outer', [PassMiddleware::class, 'inner']);
        $router->get('/nested', fn() => 'ok', ['outer']);
        $router->resolve();

        $this->assertSame(['pass', 'second'], Trace::$log);
    }

    public function test_a_self_referencing_group_is_rejected(): void
    {
        [$router] = $this->router('GET', '/cycle');
        $router->middlewareGroup('loop', ['loop']);
        $router->get('/cycle', fn() => 'ok', ['loop']);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessageMatches('/Circular/');
        $router->resolve();
    }

    public function test_alias_arguments_are_cast_to_numbers(): void
    {
        [$router] = $this->router('GET', '/aliased');
        $router->aliasMiddleware('args', ArgsMiddleware::class);
        $router->get('/aliased', fn() => 'ok', ['args:100,60']);
        $router->resolve();

        $this->assertSame(['args:100:60'], Trace::$log);
    }

    public function test_unknown_middleware_class_is_rejected(): void
    {
        [$router] = $this->router('GET', '/badmw');
        $router->get('/badmw', fn() => 'ok', ['App\\Nope\\Middleware']);

        $this->expectException(\InvalidArgumentException::class);
        $router->resolve();
    }

    public function test_security_headers_middleware_emits_all_hardening_headers(): void
    {
        $source = file_get_contents(__DIR__ . '/../../framework/src/Middlewares/SecurityHeadersMiddleware.php');

        foreach (['X-Frame-Options', 'X-Content-Type-Options', 'Referrer-Policy', 'X-XSS-Protection', 'Content-Security-Policy'] as $header) {
            $this->assertStringContainsString($header, $source);
        }
    }

    public function test_security_headers_middleware_leaves_the_response_intact(): void
    {
        $response = new Response();
        (new SecurityHeadersMiddleware())->execute($this->request(), $response);

        $this->assertSame(200, $response->getStatusCode());
    }
}
