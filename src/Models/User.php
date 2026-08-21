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
     * Fetch user count from the database safely.
     */
    public function getCount(): int
    {
        try {
            $driver = $this->db?->getAttribute(\PDO::ATTR_DRIVER_NAME);

            // Check if the table exists — syntax differs per driver
            if ($driver === 'sqlite') {
                $stmt = $this->query("SELECT name FROM sqlite_master WHERE type='table' AND name='users'");
            } else {
                $stmt = $this->query("SHOW TABLES LIKE 'users'");
            }

            if ($stmt->rowCount() === 0) {
                return 0;
            }

            $stmt = $this->query("SELECT COUNT(*) as count FROM users");
            $result = $stmt->fetch();
            return (int) ($result['count'] ?? 0);
        } catch (PDOException $e) {
            error_log("Failed to fetch user count: " . $e->getMessage());
            return 0;
        }
    }
}
