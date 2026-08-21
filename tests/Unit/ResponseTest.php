<?php

declare(strict_types=1);

namespace Spartan\Tests\Unit;

use Spartan\Response;
use Spartan\Tests\TestCase;

final class ResponseTest extends TestCase
{
    public function test_defaults_to_200(): void
    {
        $this->assertSame(200, (new Response())->getStatusCode());
    }

    public function test_status_headers_and_content_round_trip(): void
    {
        $response = new Response();
        $response->setStatusCode(418);
        $response->setHeader('X-Teapot', 'short');
        $response->setContent('hi');

        $this->assertSame(418, $response->getStatusCode());
        $this->assertSame(['X-Teapot' => 'short'], $response->getHeaders());
        $this->assertSame('hi', $response->getContent());
    }

    public function test_json_sets_body_status_and_content_type(): void
    {
        $response = new Response();
        $response->json(['ok' => true], 201);

        $this->assertSame(201, $response->getStatusCode());
        $this->assertSame('application/json; charset=utf-8', $response->getHeaders()['Content-Type']);
        $this->assertSame('{"ok":true}', $response->getContent());
    }

    public function test_redirect_sets_302_and_location(): void
    {
        $response = new Response();
        $response->redirect('/dashboard');

        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame('/dashboard', $response->getRedirectUrl());
        $this->assertSame('/dashboard', $response->getHeaders()['Location']);
    }

    public function test_redirect_blocks_an_external_host(): void
    {
        $response = new Response();
        $response->redirect('https://evil.example.com/steal');

        $this->assertSame('/', $response->getRedirectUrl(), 'open redirects must be refused');
    }

    public function test_redirect_allows_the_configured_app_url(): void
    {
        $response = new Response();
        $response->redirect('http://localhost:8000/ok');

        $this->assertSame('http://localhost:8000/ok', $response->getRedirectUrl());
    }

    public function test_redirect_keeps_a_preset_permanent_status(): void
    {
        $response = new Response();
        $response->setStatusCode(301);
        $response->redirect('/moved');

        $this->assertSame(301, $response->getStatusCode());
    }

    public function test_reset_clears_every_field(): void
    {
        $response = new Response();
        $response->setStatusCode(404);
        $response->setHeader('X', 'y');
        $response->setContent('x');
        $response->reset();

        $this->assertSame(200, $response->getStatusCode());
        $this->assertNull($response->getContent());
        $this->assertSame([], $response->getHeaders());
        $this->assertNull($response->getRedirectUrl());
    }
}
