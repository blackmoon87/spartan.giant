<?php

declare(strict_types=1);

namespace App\Listeners;

use Spartan\Application;

class NotifySubscribersListener
{
    public function handle(mixed $payload): void
    {
        $title = is_array($payload) ? ($payload['title'] ?? '') : '';
        Application::$app->logger->info("Async Worker Listener: Subscriber email newsletter dispatched for '{title}'", [
            'title' => $title,
        ]);
    }
}
