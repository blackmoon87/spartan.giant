<?php

declare(strict_types=1);

namespace App\Models;

use Spartan\Model;

class Attachment extends Model
{
    protected string $table = 'attachments';

    public function forTicket(int $ticketId): array
    {
        return $this->table()->where('ticket_id', $ticketId)->orderBy('id')->get();
    }

    public function add(int $ticketId, int $userId, array $file): string|false
    {
        return $this->create([
            'ticket_id'     => $ticketId,
            'user_id'       => $userId,
            'original_name' => $file['original_name'],
            'stored_name'   => $file['stored_name'],
            'mime'          => $file['mime'],
            'size'          => $file['size'],
            'created_at'    => gmdate('Y-m-d H:i:s'),
        ]);
    }
}
