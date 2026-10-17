<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Models\Activity;

/** Sync listener for 'ticket.activity': appends to the ticket's history. */
class RecordActivity
{
    public function handle(mixed $payload): void
    {
        (new Activity())->record(
            (int) $payload['ticket_id'],
            isset($payload['user_id']) ? (int) $payload['user_id'] : null,
            (string) $payload['action'],
            isset($payload['details']) ? (string) $payload['details'] : null
        );
    }
}
