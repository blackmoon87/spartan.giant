<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\Notification;

class NotificationController extends AppController
{
    public function index(): string
    {
        $rows = (new Notification())->forUser((int) $this->user()->id);

        // Stored as translation key + params: translated now, in the reader's locale.
        $items = array_map(function (array $n): array {
            $params = json_decode((string) $n['params'], true) ?: [];
            if (isset($params['status'])) {
                $params['status'] = trans('app.status.' . $params['status']);
            }
            return [
                'ticket_id'  => (int) $n['ticket_id'],
                'message'    => trans($n['message_key'], $params),
                'unread'     => $n['read_at'] === null,
                'created_at' => $n['created_at'],
            ];
        }, $rows);

        return $this->render('notifications/index', ['items' => $items]);
    }

    public function markRead(): void
    {
        (new Notification())->markAllRead((int) $this->user()->id);
        $this->session->setFlash('success', trans('notifications.marked_read'));
        $this->redirect('/notifications');
    }
}
