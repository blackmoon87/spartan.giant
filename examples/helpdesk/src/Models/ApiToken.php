<?php

declare(strict_types=1);

namespace App\Models;

use Spartan\Model;

/**
 * Personal API tokens. Only a SHA-256 hash is stored; the plain token is
 * shown to its owner once, when it is issued.
 */
class ApiToken extends Model
{
    protected string $table = 'api_tokens';

    /** Issue a token and return the plain value. */
    public function issue(int $userId, string $name): string
    {
        $plain = bin2hex(random_bytes(32));
        $this->create([
            'user_id'    => $userId,
            'name'       => $name,
            'token_hash' => hash('sha256', $plain),
            'created_at' => gmdate('Y-m-d H:i:s'),
        ]);
        return $plain;
    }

    /** The token row for a plain token, or null. Marks it as used. */
    public function findByPlain(string $plain): ?array
    {
        if ($plain === '') {
            return null;
        }
        $row = $this->table()->where('token_hash', hash('sha256', $plain))->first();
        if ($row !== null) {
            $this->table()->where('id', $row['id'])->update(['last_used_at' => gmdate('Y-m-d H:i:s')]);
        }
        return $row;
    }

    public function forUser(int $userId): array
    {
        return $this->table()
            ->select('id', 'name', 'created_at', 'last_used_at')
            ->where('user_id', $userId)
            ->orderBy('id', 'DESC')
            ->get();
    }

    /** Delete a token, but only one that belongs to $userId. */
    public function revoke(int $tokenId, int $userId): bool
    {
        return $this->table()->where('id', $tokenId)->where('user_id', $userId)->delete() > 0;
    }
}
