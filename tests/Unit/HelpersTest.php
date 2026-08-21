<?php

declare(strict_types=1);

namespace Spartan\Tests\Unit;

use Spartan\AuthInterface;
use Spartan\Tests\TestCase;

final class HelpersTest extends TestCase
{
    public function test_url_builds_a_root_relative_path(): void
    {
        $this->assertSame('/assets/app.css', url('assets/app.css'));
        $this->assertSame('/assets/app.css', url('/assets/app.css'));
    }

    public function test_asset_delegates_to_url(): void
    {
        $this->assertSame('/img/logo.png', asset('img/logo.png'));
    }

    public function test_auth_returns_the_auth_service(): void
    {
        $this->assertInstanceOf(AuthInterface::class, auth());
    }
}
