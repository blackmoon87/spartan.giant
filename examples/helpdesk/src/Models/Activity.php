<?php

declare(strict_types=1);

namespace App\Models;

use Spartan\Model;
use Spartan\RelationQuery;

class Activity extends Model
{
    protected string $table = 'activities';

    public function actor(): RelationQuery
    {
        return $this->belongsTo(User::class, foreignKey: 'user_id');
    }

    public function record(int $ticketId, ?int $userId, string $action, ?string $details = null): void
    {
        $this->create([
            'ticket_id'  => $ticketId,
            'user_id'    => $userId,
            'action'     => $action,
            'details'    => $details,
            'created_at' => gmdate('Y-m-d H:i:s'),
        ]);
    }

    public function forTicket(int $ticketId): array
    {
        $rows = $this->table()->where('ticket_id', $ticketId)->orderBy('id', 'DESC')->get();
        return $this->actor()->loadFor($rows, 'actor');
    }
}
