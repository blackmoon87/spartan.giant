<?php

declare(strict_types=1);

namespace App\Listeners;

use Spartan\Application;

/**
 * Async listener — queued via JobQueue.
 * Simulates sending a notification email to the task assignee.
 */
class NotifyAssigneeListener
{
    public function handle(mixed $payload): void
    {
        // In production this would send an email/push notification.
        // For demo purposes, log the notification.
        Application::$app->logger->info('Notification sent to user {user} for task "{task}"', [
            'user' => $payload['assigned_to'] ?? 'unknown',
            'task' => $payload['title'] ?? 'unknown',
        ]);
    }
}
