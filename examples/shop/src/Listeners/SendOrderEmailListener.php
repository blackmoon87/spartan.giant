<?php

declare(strict_types=1);

namespace App\Listeners;

use Spartan\Application;

class SendOrderEmailListener
{
    public function handle(mixed $payload): void
    {
        $orderId = is_array($payload) ? ($payload['id'] ?? null) : null;
        Application::$app->logger->info("Async Worker Listener: Order confirmation email dispatched for order #{order_id}", [
            'order_id' => $orderId,
        ]);
    }
}
