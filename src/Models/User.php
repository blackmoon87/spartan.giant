<?php

declare(strict_types=1);

namespace App\Models;

use Spartan\Model;
use PDOException;

class User extends Model
{
    use \Spartan\Traits\HasAuthorization;

    protected string $table = 'users';

    /**
     * Fetch the user count, or 0 when the table does not exist yet
     * (migrations not run). Works on every PDO driver.
     */
    public function getCount(): int
    {
        try {
            $result = $this->query('SELECT COUNT(*) AS count FROM users')->fetch();
            return is_array($result) ? (int) ($result['count'] ?? 0) : 0;
        } catch (PDOException $e) {
            error_log('Failed to fetch user count: ' . $e->getMessage());
            return 0;
        }
    }
}
