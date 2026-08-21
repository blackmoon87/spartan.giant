<?php

declare(strict_types=1);

namespace Spartan\Tests\Unit;

use Spartan\Middlewares\CsrfMiddleware;
use Spartan\Response;
use Spartan\Tests\TestCase;

final class CsrfTest extends TestCase
{
    private const TOKEN = 'a1b2c3d4e5f6a1b2c3d4e5f6a1b2c3d4e5f6a1b2c3d4e5f6a1b2c3d4e5f6a1b2';

    protected function setUp(): void
    {
        parent::setUp();
        $this->app->session->set('_csrf_token', self::TOKEN);
    }

    public function test_safe_methods_need_no_token(): void
    {
        $this->assertTrue($this->request('GET', '/x')->validateCsrf());
    }

    /**
     * @dataProvider stateChangingMethods
     */
    public function test_state_changing_methods_require_a_token(string $method): void
    {
        $this->assertFalse(
            $this->request($method, '/x')->validateCsrf(),
            "{$method} without a token must be rejected"
        );
    }

    /**
     * @dataProvider stateChangingMethods
     */
    public function test_state_changing_methods_accept_a_header_token(string $method): void
    {
        $request = $this->request($method, '/x', [], ['HTTP_X_CSRF_TOKEN' => self::TOKEN]);

        $this->assertTrue($request->validateCsrf());
    }

    public static function stateChangingMethods(): array
    {
        return [['POST'], ['PUT'], ['PATCH'], ['DELETE']];
    }

    public function test_accepts_a_valid_form_token(): void
    {
        $this->assertTrue($this->request('POST', '/x', ['_csrf' => self::TOKEN])->validateCsrf());
    }

    public function test_rejects_a_wrong_form_token(): void
    {
        $this->assertFalse($this->request('POST', '/x', ['_csrf' => 'wrong'])->validateCsrf());
    }

    public function test_rejects_a_wrong_header_token(): void
    {
        $this->assertFalse($this->request('POST', '/x', [], ['HTTP_X_CSRF_TOKEN' => 'wrong'])->validateCsrf());
    }

    public function test_spoofed_delete_is_still_verified(): void
    {
        $this->assertFalse($this->request('POST', '/x', ['_method' => 'DELETE'])->validateCsrf());
    }

    public function test_middleware_answers_403_for_a_browser_request(): void
    {
        $response = new Response();
        (new CsrfMiddleware())->execute($this->request('POST', '/x'), $response);

        $this->assertSame(403, $response->getStatusCode(), 'a bad token is a client error, not a 500');
        $this->assertStringContainsString('403', (string) $response->getContent());
    }

    public function test_middleware_answers_json_403_for_ajax(): void
    {
        $response = new Response();
        $request  = $this->request('POST', '/x', [], ['HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest']);

        (new CsrfMiddleware())->execute($request, $response);

        $this->assertSame(403, $response->getStatusCode());
        $this->assertStringContainsString('CSRF', (string) $response->getContent());
    }

    public function test_middleware_passes_a_valid_token_through(): void
    {
        $response = new Response();
        (new CsrfMiddleware())->execute($this->request('POST', '/x', ['_csrf' => self::TOKEN]), $response);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertNull($response->getContent());
    }

    public function test_middleware_honours_router_exclusions(): void
    {
        $this->app->router->excludeCsrf('/hooks/*');

        $response = new Response();
        (new CsrfMiddleware())->execute($this->request('POST', '/hooks/stripe'), $response);

        $this->assertSame(200, $response->getStatusCode());
    }

    public function test_token_comparison_is_timing_safe(): void
    {
        $source = file_get_contents(__DIR__ . '/../../framework/src/Request.php');

        $this->assertStringContainsString('hash_equals', $source);
    }
}
