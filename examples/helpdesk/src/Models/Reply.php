<?php

declare(strict_types=1);

namespace App\Models;

use Spartan\Model;
use Spartan\RelationQuery;

class Reply extends Model
{
    protected string $table = 'replies';
    protected bool $timestamps = true;

    public function author(): RelationQuery
    {
        return $this->belongsTo(User::class, foreignKey: 'user_id');
    }

    /** Replies in posting order, with their authors; internal notes only for staff. */
    public function forTicket(int $ticketId, bool $includeInternal): array
    {
        $query = $this->table()->where('ticket_id', $ticketId);
        if (!$includeInternal) {
            $query->where('is_internal', 0);
        }
        return $this->author()->loadFor($query->orderBy('id')->get(), 'author');
    }
}
