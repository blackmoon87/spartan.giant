<?php

declare(strict_types=1);

namespace Spartan\Tests\Unit;

use Spartan\ExceptionHandler;
use Spartan\Response;
use Spartan\Tests\TestCase;

final class ExceptionHandlerTest extends TestCase
{
    private function handle(\Throwable $e, bool $debug, array $server = []): array
    {
        $response = new Response();
        $request  = $this->request('GET', '/boom', [], $server);

        ob_start();
        (new ExceptionHandler())->handle($e, $request, $response, ['app' => ['debug' => $debug]]);
        $output = (string) ob_get_clean();

        return [$response, $output];
    }

    public function test_returns_500(): void
    {
        [$response] = $this->handle(new \RuntimeException('kaboom'), false);

        $this->assertSame(500, $response->getStatusCode());
    }

    public function test_hides_internals_when_debug_is_off(): void
    {
        [, $output] = $this->handle(new \RuntimeException('secret-internal-detail'), false);

        $this->assertStringNotContainsString('secret-internal-detail', $output);
    }

    public function test_shows_the_message_and_trace_when_debug_is_on(): void
    {
        [, $output] = $this->handle(new \RuntimeException('visible-detail'), true);

        $this->assertStringContainsString('visible-detail', $output);
    }

    public function test_answers_ajax_requests_with_json(): void
    {
        [$response] = $this->handle(new \RuntimeException('x'), false, ['HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest']);

        $this->assertStringContainsString('Internal Server Error', (string) $response->getContent());
        $this->assertJson((string) $response->getContent());
    }

    public function test_ajax_response_hides_internals_when_debug_is_off(): void
    {
        [$response] = $this->handle(new \RuntimeException('secret-detail'), false, ['HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest']);

        $this->assertStringNotContainsString('secret-detail', (string) $response->getContent());
    }

    public function test_the_exception_is_logged(): void
    {
        $this->handle(new \RuntimeException('audit-logged-error'), false);

        $file = \Spartan\Paths::storage('logs') . '/app-' . date('Y-m-d') . '.log';

        $this->assertFileExists($file);
        $this->assertStringContainsString('audit-logged-error', file_get_contents($file));
    }
}
