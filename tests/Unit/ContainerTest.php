<?php

declare(strict_types=1);

namespace Spartan\Tests\Unit;

use Spartan\AuthInterface;
use Spartan\Container;
use Spartan\Logger;
use Spartan\Tests\Fixtures\HasDefault;
use Spartan\Tests\Fixtures\NeedsLogger;
use Spartan\Tests\Fixtures\Unresolvable;
use Spartan\Tests\TestCase;

final class ContainerTest extends TestCase
{
    private Container $container;

    protected function setUp(): void
    {
        parent::setUp();
        $this->container = new Container();
    }

    public function test_bind_returns_a_new_instance_per_call(): void
    {
        $this->container->bind('svc', fn() => new \stdClass());

        $this->assertNotSame($this->container->make('svc'), $this->container->make('svc'));
    }

    public function test_singleton_returns_the_same_instance(): void
    {
        $this->container->singleton('svc', fn() => new \stdClass());

        $this->assertSame($this->container->make('svc'), $this->container->make('svc'));
    }

    public function test_instance_registers_a_prebuilt_object(): void
    {
        $object = new \stdClass();
        $this->container->instance('pre', $object);

        $this->assertTrue($this->container->has('pre'));
        $this->assertSame($object, $this->container->make('pre'));
    }

    public function test_forget_removes_a_binding(): void
    {
        $this->container->instance('gone', new \stdClass());
        $this->container->forget('gone');

        $this->assertFalse($this->container->has('gone'));
    }

    public function test_auto_resolves_a_class_without_a_binding(): void
    {
        $this->assertInstanceOf(Logger::class, $this->container->make(Logger::class));
    }

    public function test_auto_resolves_constructor_dependencies(): void
    {
        $resolved = $this->container->make(NeedsLogger::class);

        $this->assertInstanceOf(NeedsLogger::class, $resolved);
        $this->assertInstanceOf(Logger::class, $resolved->logger);
    }

    public function test_uses_default_values_for_scalar_parameters(): void
    {
        $this->assertSame(7, $this->container->make(HasDefault::class)->depth);
    }

    public function test_rejects_an_unknown_class(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->container->make('No\\Such\\Class');
    }

    public function test_rejects_an_interface_without_a_binding(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->container->make(AuthInterface::class);
    }

    public function test_rejects_an_unresolvable_scalar_parameter(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->container->make(Unresolvable::class);
    }

    public function test_reuses_cached_constructor_metadata(): void
    {
        $first = $this->container->make(NeedsLogger::class);

        for ($i = 0; $i < 100; $i++) {
            $this->container->make(NeedsLogger::class);
        }

        $this->assertInstanceOf(NeedsLogger::class, $first);
    }
}
