<?php

declare(strict_types=1);

namespace Spartan\Tests\Unit;

use Spartan\AuthInterface;
use Spartan\Tests\TestCase;

final class AuthTest extends TestCase
{
    public function test_reports_a_logged_out_visitor(): void
    {
        $this->assertFalse($this->app->auth->check());
        $this->assertNull($this->app->auth->user());
        $this->assertNull($this->app->auth->id());
    }

    public function test_resolves_the_user_from_the_session(): void
    {
        $this->app->session->set('user_id', 1);
        $this->app->auth->forgetUser();

        $user = $this->app->auth->user();

        $this->assertNotNull($user);
        $this->assertSame('ada@example.com', $user->email);
        $this->assertSame(1, (int) $this->app->auth->id());
        $this->assertTrue($this->app->auth->check());
    }

    public function test_switching_the_session_user_re_resolves(): void
    {
        $this->app->session->set('user_id', 1);
        $this->app->auth->forgetUser();
        $this->app->auth->user();

        $this->app->session->set('user_id', 2);

        $this->assertSame('linus@example.com', $this->app->auth->user()->email, 'the cache must not serve a stale identity');
    }

    public function test_forget_user_clears_the_cache(): void
    {
        $this->app->session->set('user_id', 1);
        $this->app->auth->user();

        $this->app->session->remove('user_id');
        $this->app->auth->forgetUser();

        $this->assertNull($this->app->auth->user());
    }

    public function test_unknown_user_id_resolves_to_null(): void
    {
        $this->app->session->set('user_id', 99999);
        $this->app->auth->forgetUser();

        $this->assertNull($this->app->auth->user());
    }

    public function test_the_auth_helper_returns_the_service(): void
    {
        $this->assertInstanceOf(AuthInterface::class, auth());
        $this->assertSame($this->app->auth, auth());
    }
}
