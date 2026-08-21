<?php

declare(strict_types=1);

namespace App\Listeners;

use Spartan\Application;

class PingSearchEnginesListener
{
    public function handle(mixed $payload): void
    {
        $slug = is_array($payload) ? ($payload['slug'] ?? '') : '';
        Application::$app->logger->info("Async Worker Listener: Search engine sitemap ping sent for slug '/post/{slug}'", [
            'slug' => $slug,
        ]);
    }
}
