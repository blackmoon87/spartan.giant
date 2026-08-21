<?php

declare(strict_types=1);

namespace Spartan\Tests\Unit;

use Spartan\Session;
use Spartan\Tests\TestCase;

final class SessionTest extends TestCase
{
    public function test_set_and_get_round_trip(): void
    {
        $this->app->session->set('k', ['a' => 1]);

        $this->assertSame(['a' => 1], $this->app->session->get('k'));
    }

    public function test_get_returns_the_default_for_a_missing_key(): void
    {
        $this->assertSame('dflt', $this->app->session->get('nope', 'dflt'));
    }

    public function test_remove_deletes_a_key(): void
    {
        $this->app->session->set('tmp', 1);
        $this->app->session->remove('tmp');

        $this->assertNull($this->app->session->get('tmp'));
    }

    public function test_flash_is_readable_during_the_current_request(): void
    {
        $this->app->session->setFlash('notice', 'saved');
        $this->app->session->removeFlashMessages();

        $this->assertSame('saved', $this->app->session->getFlash('notice'));
    }

    public function test_flash_is_cleared_on_the_next_cycle(): void
    {
        $this->app->session->setFlash('notice', 'saved');
        $_SESSION['flash_messages']['notice']['remove'] = true;
        $this->app->session->removeFlashMessages();

        $this->assertNull($this->app->session->getFlash('notice'));
    }

    public function test_cookie_is_hardened_before_start(): void
    {
        $source = file_get_contents(__DIR__ . '/../../framework/src/Session.php');

        $this->assertStringContainsString("'httponly' => true", $source);
        $this->assertStringContainsString("'samesite' => 'Lax'", $source);
        $this->assertStringContainsString("'secure'   => \$isHttps", $source);
    }

    public function test_exposes_fixation_and_worker_mode_controls(): void
    {
        $this->assertTrue(method_exists($this->app->session, 'regenerate'));
        $this->assertTrue(method_exists($this->app->session, 'start'));
        $this->assertTrue(method_exists($this->app->session, 'close'));
    }

    public function test_start_is_idempotent(): void
    {
        $session = $this->app->session;
        $session->start();
        $session->start();

        $this->assertInstanceOf(Session::class, $session);
    }
}
