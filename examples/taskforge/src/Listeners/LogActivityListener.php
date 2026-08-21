<?php

declare(strict_types=1);

namespace App\Listeners;

use Spartan\Application;
use Spartan\QueryBuilder;

/**
 * Sync listener — logs every task.completed event into the activity_logs table.
 */
class LogActivityListener
{
    public function handle(mixed $payload): void
    {
        $db = Application::$app->db;
        (new QueryBuilder($db, 'activity_logs'))->insert([
            'user_id'     => $payload['assigned_to'] ?? null,
            'action'      => 'task.completed',
            'entity_type' => 'task',
            'entity_id'   => $payload['id'] ?? 0,
            'details'     => json_encode($payload),
        ]);
    }
}
