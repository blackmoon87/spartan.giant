<?php

declare(strict_types=1);

namespace App\Listeners;

use Spartan\Application;

class NotifyAdminListener
{
    public function handle(mixed $payload): void
    {
        $orderId = is_array($payload) ? ($payload['id'] ?? null) : null;
        Application::$app->logger->info("Async Worker Listener: Admin dashboard notification sent for new order #{order_id}", [
            'order_id' => $orderId,
        ]);
    }
}
