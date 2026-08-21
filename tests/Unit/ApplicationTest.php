<?php

declare(strict_types=1);

namespace Spartan\Tests\Unit;

use PDO;
use Spartan\Application;
use Spartan\Auth;
use Spartan\Container;
use Spartan\EventDispatcher;
use Spartan\Gate;
use Spartan\Logger;
use Spartan\Paths;
use Spartan\Request;
use Spartan\Response;
use Spartan\Router;
use Spartan\Session;
use Spartan\Tests\TestCase;
use Spartan\View;

final class ApplicationTest extends TestCase
{
    public function test_the_global_instance_is_set(): void
    {
        $this->assertSame($this->app, Application::$app);
    }

    public function test_every_core_service_is_wired(): void
    {
        $this->assertInstanceOf(Router::class, $this->app->router);
        $this->assertInstanceOf(Request::class, $this->app->request);
        $this->assertInstanceOf(Response::class, $this->app->response);
        $this->assertInstanceOf(View::class, $this->app->view);
        $this->assertInstanceOf(Session::class, $this->app->session);
        $this->assertInstanceOf(Auth::class, $this->app->auth);
        $this->assertInstanceOf(Container::class, $this->app->container);
        $this->assertInstanceOf(EventDispatcher::class, $this->app->events);
        $this->assertInstanceOf(Logger::class, $this->app->logger);
    }

    public function test_the_database_connects_lazily(): void
    {
        $this->assertInstanceOf(PDO::class, $this->app->db);
        $this->assertSame('sqlite', $this->app->db->getAttribute(PDO::ATTR_DRIVER_NAME));
    }

    public function test_a_second_instantiation_is_refused(): void
    {
        $this->expectException(\LogicException::class);
        new Application(['app' => []]);
    }

    public function test_a_csrf_token_is_generated_at_boot(): void
    {
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', (string) $this->app->session->get('_csrf_token'));
    }

    public function test_the_project_root_is_declared(): void
    {
        $this->assertSame($this->work, Paths::base());
        $this->assertSame($this->work . '/storage/logs', Paths::storage('logs'));
    }

    public function test_helpers_are_loaded(): void
    {
        $this->assertTrue(function_exists('url'));
        $this->assertTrue(function_exists('asset'));
        $this->assertTrue(function_exists('auth'));
    }

    // ─── Worker mode ────────────────────────────────────────────────────────

    public function test_reset_clears_the_cached_identity(): void
    {
        $this->app->container->instance('auth_user', (object) ['id' => 4242]);

        $this->app->resetPerRequestState();

        $this->assertFalse($this->app->container->has('auth_user'), 'identity must never survive into the next request');
        $this->assertNull(Gate::resolveUser());
    }

    public function test_reset_clears_the_auth_user_cache(): void
    {
        $this->app->session->set('user_id', 1);
        $this->app->auth->user();

        $this->app->session->remove('user_id');
        $this->app->resetPerRequestState();

        $this->assertNull($this->app->auth->user());
    }

    public function test_reset_clears_view_render_state(): void
    {
        $this->app->resetPerRequestState();

        $property = new \ReflectionProperty(View::class, 'sections');
        $property->setAccessible(true);

        $this->assertSame([], $property->getValue($this->app->view));
    }

    public function test_worker_entrypoints_exist(): void
    {
        $this->assertTrue(method_exists($this->app, 'handleRequest'));
        $this->assertTrue(method_exists($this->app, 'resetPerRequestState'));
        $this->assertTrue(method_exists($this->app->request, 'resetState'));
        $this->assertTrue(method_exists($this->app->session, 'close'));
    }

    public function test_the_worker_loop_recycles_the_process(): void
    {
        $source = file_get_contents(__DIR__ . '/../../public/index.php');

        $this->assertStringContainsString('FRANKENPHP_MAX_REQUESTS', $source);
    }
}
