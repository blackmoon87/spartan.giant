<?php

declare(strict_types=1);

namespace App\Models;

use Spartan\Model;

class Notification extends Model
{
    protected string $table = 'notifications';

    /**
     * Store a notification once per $eventKey. The queue delivers at least
     * once, so a retried job calls this again with the same key: no duplicate.
     * The message is stored as a translation key + params and translated when
     * shown, so each reader sees it in their own language.
     */
    public function deliverOnce(int $userId, int $ticketId, string $messageKey, array $params, string $eventKey): void
    {
        if ($this->table()->where('event_key', $eventKey)->exists()) {
            return;
        }
        $this->create([
            'user_id'     => $userId,
            'ticket_id'   => $ticketId,
            'message_key' => $messageKey,
            'params'      => json_encode($params),
            'event_key'   => $eventKey,
            'created_at'  => gmdate('Y-m-d H:i:s'),
        ]);
    }

    public function forUser(int $userId, int $limit = 50): array
    {
        return $this->table()->where('user_id', $userId)->orderBy('id', 'DESC')->limit($limit)->get();
    }

    public function unreadCount(int $userId): int
    {
        return $this->table()->where('user_id', $userId)->whereNull('read_at')->count();
    }

    public function markAllRead(int $userId): int
    {
        return $this->table()
            ->where('user_id', $userId)
            ->whereNull('read_at')
            ->update(['read_at' => gmdate('Y-m-d H:i:s')]);
    }
}
