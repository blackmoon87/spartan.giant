<?php

declare(strict_types=1);

namespace Spartan\Tests\Unit;

use Spartan\AuthInterface;
use Spartan\SessionInterface;
use Spartan\Tests\Fixtures\DeniedRequest;
use Spartan\Tests\Fixtures\EmailRequest;
use Spartan\Tests\TestCase;

final class FormRequestTest extends TestCase
{
    public function test_binds_session_and_auth_on_construction(): void
    {
        $this->request('POST', '/x', ['email' => 'a@b.co']);
        $formRequest = new EmailRequest();

        $this->assertInstanceOf(SessionInterface::class, $formRequest->session);
        $this->assertInstanceOf(AuthInterface::class, $formRequest->auth);
    }

    public function test_valid_input_passes_validation(): void
    {
        $this->request('POST', '/x', ['email' => 'a@b.co']);

        (new EmailRequest())->validate();

        $this->expectNotToPerformAssertions();
    }

    public function test_invalid_input_is_rejected(): void
    {
        $this->request('POST', '/x', ['email' => 'not-an-email']);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/Validation Failed/');
        (new EmailRequest())->validate();
    }

    public function test_failed_validation_flashes_errors_and_old_input(): void
    {
        $this->request('POST', '/x', ['email' => 'nope']);

        try {
            (new EmailRequest())->validate();
        } catch (\RuntimeException) {
            // expected
        }

        $this->assertArrayHasKey('email', (array) $this->app->session->getFlash('validation_errors'));
        $this->assertSame(['email' => 'nope'], $this->app->session->getFlash('old_input'));
    }

    public function test_authorize_false_blocks_the_request(): void
    {
        $this->request('POST', '/x', ['email' => 'a@b.co']);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/Authorization Failed/');
        (new DeniedRequest())->validate();
    }

    public function test_inherits_request_accessors(): void
    {
        $this->request('POST', '/x', ['email' => 'a@b.co', 'extra' => 'v']);
        $formRequest = new EmailRequest();

        $this->assertSame('v', $formRequest->post('extra'));
        $this->assertSame('POST', $formRequest->getMethod());
    }
}
