<?php

declare(strict_types=1);

namespace Spartan\Middlewares;

use Spartan\Middleware;
use Spartan\Request;
use Spartan\Response;
use Spartan\Cache;

use Spartan\Application;

class RateLimitMiddleware extends Middleware
{
    private int $limit;
    private int $window;

    public function __construct(?int $limit = null, ?int $window = null)
    {
        $config = Application::$app->config['rate_limit'] ?? [];
        $this->limit = $limit ?? $config['default_limit'] ?? 60;
        $this->window = $window ?? $config['default_window'] ?? 60;
    }

    public function execute(Request $request, Response $response): void
    {
        $ip = $request->getIp();
        $path = $request->getPath();
        $key = 'rate_limit:' . md5($ip . ':' . $path);

        // Atomic increment — a get()/put() pair drops concurrent hits and lets
        // parallel clients sail past the configured limit.
        [$hits, $resetAt] = Cache::increment($key, $this->window);

        $currentTime  = time();
        $remainingTtl = max(1, $resetAt - $currentTime);
        $remaining    = max(0, $this->limit - $hits);

        $response->setHeader('X-RateLimit-Limit', (string) $this->limit);
        $response->setHeader('X-RateLimit-Remaining', (string) $remaining);
        $response->setHeader('X-RateLimit-Reset', (string) $resetAt);

        if ($hits > $this->limit) {
            $response->setHeader('Retry-After', (string) $remainingTtl);
            $response->setStatusCode(429);

            if ($request->isAjax()) {
                $response->json(['error' => 'Too many requests. Please try again later.'], 429);
            } else {
                $response->setHeader('Content-Type', 'text/html; charset=utf-8');
                $response->setContent('<h1>429 Too Many Requests</h1><p>Too many requests. Please try again later.</p>');
            }
        }
    }
}
