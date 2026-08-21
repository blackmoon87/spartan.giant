<?php

declare(strict_types=1);

namespace Spartan\Tests\Unit;

use Spartan\Request;
use Spartan\Tests\TestCase;

final class RequestTest extends TestCase
{
    public function test_get_path_strips_the_query_string(): void
    {
        $this->assertSame('/blog/post', $this->request('GET', '/blog/post?x=1&y=2')->getPath());
    }

    public function test_base_path_is_empty_under_cli(): void
    {
        // getBasePath() short-circuits for the CLI SAPI: a console runner has
        // no document root to strip. Asserting the documented behaviour rather
        // than pretending the test process is a web request.
        $request = $this->request('GET', '/myapp/public/users', [], ['SCRIPT_NAME' => '/myapp/public/index.php']);

        $this->assertSame('', $request->getBasePath());
        $this->assertSame('/myapp/public/users', $request->getPath());
    }

    public function test_get_path_defaults_to_root(): void
    {
        $this->assertSame('/', $this->request()->getPath());
    }

    /**
     * @dataProvider spoofedMethods
     */
    public function test_form_method_spoofing(string $spoof, string $expected): void
    {
        $request = $this->request('POST', '/x', ['_method' => $spoof]);

        $this->assertSame($expected, $request->getMethod());
        $this->assertSame('POST', $request->getRealMethod(), 'the real verb must stay POST');
    }

    public static function spoofedMethods(): array
    {
        return [
            'put'            => ['put', 'PUT'],
            'PATCH'          => ['PATCH', 'PATCH'],
            'DELETE'         => ['DELETE', 'DELETE'],
            'unsupported'    => ['TRACE', 'POST'],
        ];
    }

    public function test_is_secure_detects_https(): void
    {
        $this->assertFalse($this->request()->isSecure());
        $this->assertTrue($this->request('GET', '/', [], ['HTTPS' => 'on'])->isSecure());
        $this->assertTrue($this->request('GET', '/', [], ['SERVER_PORT' => 443])->isSecure());
    }

    public function test_reads_query_body_and_json_input(): void
    {
        $request = $this->request('POST', '/x', ['name' => 'Ada']);
        $_GET    = ['q' => 'spartan', 'name' => 'from-query'];

        $this->assertSame('spartan', $request->get('q'));
        $this->assertSame('Ada', $request->post('name'));
        $this->assertSame('Ada', $request->input('name'), 'body must win over query');
        $this->assertSame('Ada', $request->getParam('name'));
        $this->assertSame('fallback', $request->input('missing', 'fallback'));
    }

    public function test_get_body_merges_every_source(): void
    {
        $request = $this->request('POST', '/x', ['a' => 1]);
        $_GET    = ['b' => 2];

        $this->assertSame(['b' => 2, 'a' => 1], $request->getBody());
    }

    public function test_header_lookup_is_case_insensitive(): void
    {
        $request = $this->request('GET', '/', [], ['HTTP_X_CUSTOM' => 'yes']);

        $this->assertSame('yes', $request->header('X-Custom'));
        $this->assertSame('yes', $request->header('x-custom'));
        $this->assertNull($request->header('X-Absent'));
    }

    public function test_detects_ajax_and_htmx_requests(): void
    {
        $this->assertTrue($this->request('GET', '/', [], ['HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest'])->isAjax());
        $this->assertTrue($this->request('GET', '/', [], ['HTTP_HX_REQUEST' => 'true'])->isAjax());
        $this->assertFalse($this->request()->isAjax());
    }

    public function test_reads_uploaded_files(): void
    {
        $request = $this->request();
        $_FILES  = ['avatar' => ['name' => 'a.png', 'size' => 10]];

        $this->assertSame('a.png', $request->file('avatar')['name']);
        $this->assertCount(1, $request->getFiles());
        $this->assertNull($request->file('missing'));
    }

    // ─── Client IP / trusted proxies ────────────────────────────────────────

    public function test_forwarded_headers_are_ignored_without_trusted_proxies(): void
    {
        Request::setTrustedProxies([]);

        $request = $this->request('GET', '/', [], [
            'REMOTE_ADDR'          => '198.51.100.7',
            'HTTP_X_FORWARDED_FOR' => '1.2.3.4',
            'HTTP_CLIENT_IP'       => '9.9.9.9',
        ]);

        $this->assertSame('198.51.100.7', $request->getIp(), 'a forged header must not become the client IP');
    }

    public function test_forwarded_header_is_honoured_for_an_exact_trusted_proxy(): void
    {
        Request::setTrustedProxies(['198.51.100.7']);

        $request = $this->request('GET', '/', [], [
            'REMOTE_ADDR'          => '198.51.100.7',
            'HTTP_X_FORWARDED_FOR' => '1.2.3.4',
        ]);

        $this->assertSame('1.2.3.4', $request->getIp());
    }

    public function test_forwarded_header_is_honoured_for_a_cidr_range(): void
    {
        Request::setTrustedProxies(['198.51.100.0/24']);

        $request = $this->request('GET', '/', [], [
            'REMOTE_ADDR'          => '198.51.100.7',
            'HTTP_X_FORWARDED_FOR' => '203.0.113.5, 198.51.100.7',
        ]);

        $this->assertSame('203.0.113.5', $request->getIp(), 'the left-most entry is the original client');
    }

    public function test_cidr_miss_is_not_trusted(): void
    {
        Request::setTrustedProxies(['10.0.0.0/8']);

        $request = $this->request('GET', '/', [], [
            'REMOTE_ADDR'          => '198.51.100.7',
            'HTTP_X_FORWARDED_FOR' => '1.2.3.4',
        ]);

        $this->assertSame('198.51.100.7', $request->getIp());
    }

    public function test_wildcard_trusts_every_peer(): void
    {
        Request::setTrustedProxies(['*']);

        $request = $this->request('GET', '/', [], [
            'REMOTE_ADDR'          => '198.51.100.7',
            'HTTP_X_FORWARDED_FOR' => '1.2.3.4',
        ]);

        $this->assertSame('1.2.3.4', $request->getIp());
    }

    public function test_malformed_forwarded_value_falls_back_to_remote_addr(): void
    {
        Request::setTrustedProxies(['*']);

        $request = $this->request('GET', '/', [], [
            'REMOTE_ADDR'          => '198.51.100.7',
            'HTTP_X_FORWARDED_FOR' => 'not-an-ip',
        ]);

        $this->assertSame('198.51.100.7', $request->getIp());
    }

    public function test_reset_state_clears_memoised_body(): void
    {
        $request = $this->request();
        $request->resetState();

        $this->assertSame([], $request->getBody());
    }
}
