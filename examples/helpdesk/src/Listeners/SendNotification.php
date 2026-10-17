<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Models\Notification;

/**
 * Async listener for 'ticket.notify', run by `php spartan worker`.
 * The queue delivers at least once; deliverOnce() keys every row on
 * event_key + user, so a retried job never notifies twice.
 */
class SendNotification
{
    public function handle(mixed $payload): void
    {
        $notifications = new Notification();
        foreach ((array) $payload['user_ids'] as $userId) {
            $notifications->deliverOnce(
                (int) $userId,
                (int) $payload['ticket_id'],
                (string) $payload['message_key'],
                (array) $payload['params'],
                $payload['event_key'] . ':' . $userId
            );
        }
    }
}
