<?php

declare(strict_types=1);

namespace Spartan\Tests\Unit;

use Spartan\Tests\Fixtures\AdminController;
use Spartan\Tests\Fixtures\PlainController;
use Spartan\Tests\Fixtures\RoleUser;
use Spartan\Tests\TestCase;

final class AuthorizationAttributesTest extends TestCase
{
    public function test_anonymous_visitor_is_redirected_to_login(): void
    {
        [$router, , $response] = $this->router('GET', '/admin-area');
        $router->get('/admin-area', [AdminController::class, 'index']);
        $router->resolve();

        // A browser request is redirected (the 401 the router sets first is
        // replaced by the redirect's 302 — see the AJAX case for the 401).
        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame('/login', $response->getRedirectUrl());
        $this->assertSame('You must be logged in to access this page.', $this->app->session->getFlash('error'));
    }

    public function test_anonymous_ajax_visitor_gets_json_401(): void
    {
        [$router, , $response] = $this->router('GET', '/admin-area', ['HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest']);
        $router->get('/admin-area', [AdminController::class, 'index']);
        $router->resolve();

        $this->assertSame(401, $response->getStatusCode());
        $this->assertStringContainsString('Unauthorized', (string) $response->getContent());
    }

    public function test_wrong_role_gets_403(): void
    {
        $this->app->container->instance('auth_user', new RoleUser('user', ['manage_users']));

        [$router, , $response] = $this->router('GET', '/admin-area');
        $router->get('/admin-area', [AdminController::class, 'index']);
        $router->resolve();

        $this->assertSame(403, $response->getStatusCode());
    }

    public function test_missing_permission_gets_403(): void
    {
        $this->app->container->instance('auth_user', new RoleUser('admin', []));

        [$router, , $response] = $this->router('GET', '/admin-area');
        $router->get('/admin-area', [AdminController::class, 'index']);
        $router->resolve();

        $this->assertSame(403, $response->getStatusCode());
    }

    public function test_correct_role_and_permission_passes(): void
    {
        $this->app->container->instance('auth_user', new RoleUser('admin', ['manage_users']));

        [$router, , $response] = $this->router('GET', '/admin-area');
        $router->get('/admin-area', [AdminController::class, 'index']);

        $this->assertSame('admin-index', $router->resolve());
        $this->assertSame(200, $response->getStatusCode());
    }

    public function test_method_level_attributes_are_additive(): void
    {
        $this->app->container->instance('auth_user', new RoleUser('admin', ['manage_users']));

        [$router, , $response] = $this->router('GET', '/admin-danger');
        $router->get('/admin-danger', [AdminController::class, 'danger']);
        $router->resolve();

        $this->assertSame(403, $response->getStatusCode(), 'the method requires an extra permission');
    }

    public function test_method_level_permission_granted(): void
    {
        $this->app->container->instance('auth_user', new RoleUser('admin', ['manage_users', 'delete_everything']));

        [$router] = $this->router('GET', '/admin-danger');
        $router->get('/admin-danger', [AdminController::class, 'danger']);

        $this->assertSame('danger', $router->resolve());
    }

    public function test_unprotected_controllers_need_no_identity(): void
    {
        [$router] = $this->router('GET', '/open');
        $router->get('/open', [PlainController::class, 'plain']);

        $this->assertSame('plain-ok', $router->resolve());
    }
}
