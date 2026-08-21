<?php

declare(strict_types=1);

namespace Spartan\Tests\Unit;

use Spartan\Cache;
use Spartan\Middlewares\RateLimitMiddleware;
use Spartan\Response;
use Spartan\Tests\TestCase;

final class RateLimitTest extends TestCase
{
    private function hit(int $limit = 3, int $window = 60, array $server = []): Response
    {
        $response = new Response();
        $request  = $this->request('GET', '/rl', [], array_merge(['REMOTE_ADDR' => '203.0.113.20'], $server));

        (new RateLimitMiddleware($limit, $window))->execute($request, $response);

        return $response;
    }

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public function test_first_request_is_allowed_and_reports_quota(): void
    {
        $response = $this->hit();

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('3', $response->getHeaders()['X-RateLimit-Limit']);
        $this->assertSame('2', $response->getHeaders()['X-RateLimit-Remaining']);
        $this->assertArrayHasKey('X-RateLimit-Reset', $response->getHeaders());
    }

    public function test_remaining_quota_decrements(): void
    {
        $this->hit();

        $this->assertSame('1', $this->hit()->getHeaders()['X-RateLimit-Remaining']);
        $this->assertSame('0', $this->hit()->getHeaders()['X-RateLimit-Remaining']);
    }

    public function test_exceeding_the_limit_returns_429(): void
    {
        $this->hit();
        $this->hit();
        $this->hit();
        $response = $this->hit();

        $this->assertSame(429, $response->getStatusCode());
        $this->assertArrayHasKey('Retry-After', $response->getHeaders());
    }

    public function test_a_forged_forwarded_header_cannot_reset_the_counter(): void
    {
        for ($i = 0; $i < 4; $i++) {
            $this->hit();
        }

        $response = $this->hit(3, 60, ['HTTP_X_FORWARDED_FOR' => '5.5.5.5']);

        $this->assertSame(429, $response->getStatusCode(), 'spoofing an IP must not grant a fresh quota');
    }

    public function test_separate_clients_get_separate_buckets(): void
    {
        for ($i = 0; $i < 4; $i++) {
            $this->hit();
        }

        $this->assertSame(200, $this->hit(3, 60, ['REMOTE_ADDR' => '203.0.113.99'])->getStatusCode());
    }

    public function test_ajax_clients_receive_json(): void
    {
        $server = ['REMOTE_ADDR' => '203.0.113.77', 'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest'];
        $this->hit(1, 60, $server);
        $response = $this->hit(1, 60, $server);

        $this->assertSame(429, $response->getStatusCode());
        $this->assertStringContainsString('Too many requests', (string) $response->getContent());
    }

    public function test_the_counter_increments_atomically(): void
    {
        $seen = [];
        for ($i = 0; $i < 5; $i++) {
            [$hits] = Cache::increment('rl-atomic', 60);
            $seen[] = $hits;
        }

        $this->assertSame([1, 2, 3, 4, 5], $seen, 'read-modify-write would lose concurrent hits');
    }

    public function test_the_window_is_fixed_not_sliding(): void
    {
        [, $first]  = Cache::increment('rl-window', 60);
        [, $second] = Cache::increment('rl-window', 60);

        $this->assertSame($first, $second);
    }
}
