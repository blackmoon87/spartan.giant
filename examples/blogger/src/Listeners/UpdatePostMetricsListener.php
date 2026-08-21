<?php

declare(strict_types=1);

namespace App\Listeners;

use Spartan\Application;

class UpdatePostMetricsListener
{
    public function handle(mixed $payload): void
    {
        $title = is_array($payload) ? ($payload['title'] ?? '') : '';
        Application::$app->logger->info("Synchronous Listener: Post metrics updated for article '{title}'", [
            'title' => $title,
        ]);
    }
}
