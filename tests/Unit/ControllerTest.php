<?php

declare(strict_types=1);

namespace Spartan\Tests\Unit;

use Spartan\Logger;
use Spartan\Tests\Fixtures\PlainController;
use Spartan\Tests\Fixtures\Trace;
use Spartan\Tests\TestCase;
use Spartan\Validator;

final class ControllerTest extends TestCase
{
    private PlainController $controller;

    protected function setUp(): void
    {
        parent::setUp();
        Trace::reset();
        $this->controller = new PlainController();
    }

    public function test_core_services_are_available(): void
    {
        $this->assertTrue($this->controller->hasServices());
    }

    public function test_validate_returns_a_validator(): void
    {
        $validator = $this->controller->validate(['email' => 'a@b.co'], ['email' => 'required|email']);

        $this->assertInstanceOf(Validator::class, $validator);
        $this->assertFalse($validator->fails());
    }

    public function test_validate_reports_failures(): void
    {
        $validator = $this->controller->validate(['email' => 'bad'], ['email' => 'email']);

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('email', $validator->errors());
    }

    public function test_validate_injects_the_connection_for_the_unique_rule(): void
    {
        $validator = $this->controller->validate(['email' => 'ada@example.com'], ['email' => 'unique:t_users,email']);

        $this->assertTrue($validator->fails(), 'the duplicate must be detected');
    }

    public function test_make_resolves_from_the_container(): void
    {
        $this->assertInstanceOf(Logger::class, $this->controller->make(Logger::class));
    }

    public function test_event_dispatches_through_the_application(): void
    {
        $this->app->events->listen('controller.ping', fn() => Trace::$log[] = 'pinged');

        $this->controller->event('controller.ping');

        $this->assertSame(['pinged'], Trace::$log);
        $this->app->events->forget('controller.ping');
    }

    public function test_json_writes_to_the_response(): void
    {
        $this->app->response->reset();
        $this->controller->json(['a' => 1], 202);

        $this->assertSame(202, $this->app->response->getStatusCode());
        $this->assertSame('{"a":1}', $this->app->response->getContent());
    }

    public function test_redirect_refuses_an_external_host(): void
    {
        $this->app->response->reset();
        $this->controller->redirect('https://evil.test/x');

        $this->assertSame('/', $this->app->response->getRedirectUrl());
    }
}
