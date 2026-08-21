<?php

declare(strict_types=1);

namespace Spartan\Database;

use PDO;

class Migrator
{
    private PDO $db;
    private string $migrationsPath;

    public function __construct(PDO $db, ?string $migrationsPath = null)
    {
        $this->db = $db;
        $this->migrationsPath = $migrationsPath ?: \Spartan\Paths::base('database/migrations');
    }

    /**
     * Run all pending migrations.
     * Each invocation records a new batch number.
     */
    public function migrate(): void
    {
        $this->createMigrationsTable();

        if (!is_dir($this->migrationsPath)) {
            mkdir($this->migrationsPath, 0755, true);
        }

        $files = glob($this->migrationsPath . '/*.sql');
        if ($files === false) {
            return;
        }

        // Exclude _down.sql files — those are rollback companions
        $files = array_filter($files, fn(string $f) => !str_ends_with(basename($f), '_down.sql'));
        sort($files);

        $executed = $this->getExecutedMigrations();
        $batch    = $this->getNextBatch();
        $ran      = 0;

        foreach ($files as $file) {
            $filename = basename($file);
            if (in_array($filename, $executed, true)) {
                continue;
            }

            echo "Migrating: {$filename}...\n";
            $sql = file_get_contents($file);
            $sql = $this->translateDialect($sql);

            try {
                $queries = array_filter(array_map('trim', explode(';', $sql)));
                foreach ($queries as $query) {
                    if ($query !== '') {
                        $this->db->exec($query);
                    }
                }
                $this->logMigration($filename, $batch);
                echo "Migrated:  {$filename}\n";
                $ran++;
            } catch (\Throwable $e) {
                throw $e;
            }
        }

        if ($ran === 0) {
            echo "Nothing to migrate.\n";
        }
    }

    /**
     * Roll back the last N batches of migrations.
     *
     * Companion rollback files follow the convention:
     *   0001_create_users_table.sql      → up
     *   0001_create_users_table_down.sql  → down (rollback)
     *
     * If no _down.sql file exists, the rollback for that file is skipped
     * with a warning.
     *
     * @param int $steps Number of batches to roll back (default: 1)
     */
    public function rollback(int $steps = 1): void
    {
        $this->createMigrationsTable();

        $migrations = $this->getMigrationsToRollback($steps);

        if (empty($migrations)) {
            echo "Nothing to roll back.\n";
            return;
        }

        foreach ($migrations as $migration) {
            $filename = $migration['migration'];
            $downFile = $this->getDownFile($filename);

            if ($downFile === null) {
                echo "  ⚠️  No rollback file for {$filename} — skipping.\n";
                continue;
            }

            echo "Rolling back: {$filename}...\n";
            $sql = file_get_contents($downFile);
            $sql = $this->translateDialect($sql);

            try {
                $queries = array_filter(array_map('trim', explode(';', $sql)));
                foreach ($queries as $query) {
                    if ($query !== '') {
                        $this->db->exec($query);
                    }
                }
                $this->removeMigration($filename);
                echo "Rolled back: {$filename}\n";
            } catch (\Throwable $e) {
                echo "  ❌ Rollback failed for {$filename}: " . $e->getMessage() . "\n";
                throw $e;
            }
        }
    }

    /**
     * Roll back ALL migrations (all batches).
     */
    public function reset(): void
    {
        $this->createMigrationsTable();
        $maxBatch = $this->getMaxBatch();

        if ($maxBatch === 0) {
            echo "Nothing to reset.\n";
            return;
        }

        $this->rollback($maxBatch);
    }

    /**
     * Drop all tables and re-run all migrations from scratch.
     */
    public function fresh(): void
    {
        $this->dropAllTables();
        echo "All tables dropped.\n";
        $this->migrate();
    }

    /**
     * Return the status of all migration files.
     *
     * @return list<array{migration: string, ran: bool, batch: int|null}>
     */
    public function status(): array
    {
        $this->createMigrationsTable();

        $files = glob($this->migrationsPath . '/*.sql');
        if ($files === false) {
            return [];
        }

        $files = array_filter($files, fn(string $f) => !str_ends_with(basename($f), '_down.sql'));
        sort($files);

        $executed = $this->getExecutedMigrationsWithBatch();
        $result   = [];

        foreach ($files as $file) {
            $filename = basename($file);
            $result[] = [
                'migration' => $filename,
                'ran'       => isset($executed[$filename]),
                'batch'     => $executed[$filename] ?? null,
            ];
        }

        return $result;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Internal — Table Management
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Create the migrations tracking table if not exists.
     * Includes the batch column for rollback support.
     */
    private function createMigrationsTable(): void
    {
        $driver = $this->db->getAttribute(PDO::ATTR_DRIVER_NAME);
        if ($driver === 'sqlite') {
            $sql = "CREATE TABLE IF NOT EXISTS `migrations` (
                `id` INTEGER PRIMARY KEY AUTOINCREMENT,
                `migration` VARCHAR(255) NOT NULL,
                `batch` INTEGER NOT NULL DEFAULT 1,
                `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
            )";
        } else {
            $sql = "CREATE TABLE IF NOT EXISTS `migrations` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `migration` VARCHAR(255) NOT NULL,
                `batch` INT NOT NULL DEFAULT 1,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
        }

        $this->db->exec($sql);

        // Ensure the batch column exists (upgrade path from old schema)
        $this->ensureBatchColumn();
    }

    /**
     * Add the batch column if it doesn't exist (smooth upgrade from pre-batch schema).
     */
    private function ensureBatchColumn(): void
    {
        $driver = $this->db->getAttribute(PDO::ATTR_DRIVER_NAME);

        try {
            if ($driver === 'sqlite') {
                $columns = $this->db->query("PRAGMA table_info(`migrations`)")->fetchAll(PDO::FETCH_ASSOC);
                $hasCol  = false;
                foreach ($columns as $col) {
                    if ($col['name'] === 'batch') {
                        $hasCol = true;
                        break;
                    }
                }
                if (!$hasCol) {
                    $this->db->exec("ALTER TABLE `migrations` ADD COLUMN `batch` INTEGER NOT NULL DEFAULT 1");
                }
            } else {
                $this->db->exec("ALTER TABLE `migrations` ADD COLUMN `batch` INT NOT NULL DEFAULT 1");
            }
        } catch (\Throwable) {
            // Column already exists — MySQL throws on duplicate column add
        }
    }

    /**
     * Drop all user tables (excluding the migrations tracking table on first pass).
     */
    private function dropAllTables(): void
    {
        $driver = $this->db->getAttribute(PDO::ATTR_DRIVER_NAME);

        if ($driver === 'sqlite') {
            $tables = $this->db->query(
                "SELECT name FROM sqlite_master WHERE type='table' AND name != 'sqlite_sequence'"
            )->fetchAll(PDO::FETCH_COLUMN);

            // SQLite requires foreign_keys off to drop tables freely
            $this->db->exec("PRAGMA foreign_keys = OFF");
            foreach ($tables as $table) {
                $this->db->exec("DROP TABLE IF EXISTS \"{$table}\"");
            }
            $this->db->exec("PRAGMA foreign_keys = ON");
        } else {
            $this->db->exec("SET FOREIGN_KEY_CHECKS = 0");
            $tables = $this->db->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
            foreach ($tables as $table) {
                $this->db->exec("DROP TABLE IF EXISTS `{$table}`");
            }
            $this->db->exec("SET FOREIGN_KEY_CHECKS = 1");
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Internal — Queries
    // ─────────────────────────────────────────────────────────────────────────

    private function getExecutedMigrations(): array
    {
        $stmt = $this->db->query("SELECT `migration` FROM `migrations` ORDER BY `id` ASC");
        return $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
    }

    /**
     * @return array<string, int> filename → batch
     */
    private function getExecutedMigrationsWithBatch(): array
    {
        $stmt = $this->db->query("SELECT `migration`, `batch` FROM `migrations` ORDER BY `id` ASC");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $map  = [];
        foreach ($rows as $row) {
            $map[$row['migration']] = (int) $row['batch'];
        }
        return $map;
    }

    private function getNextBatch(): int
    {
        return $this->getMaxBatch() + 1;
    }

    private function getMaxBatch(): int
    {
        $stmt = $this->db->query("SELECT MAX(`batch`) as max_batch FROM `migrations`");
        $row  = $stmt->fetch(PDO::FETCH_ASSOC);
        return (int) ($row['max_batch'] ?? 0);
    }

    /**
     * Get migrations to roll back, ordered by batch DESC then id DESC.
     *
     * @return list<array{migration: string, batch: int}>
     */
    private function getMigrationsToRollback(int $steps): array
    {
        $maxBatch   = $this->getMaxBatch();
        $cutoff     = max(1, $maxBatch - $steps + 1);
        $stmt       = $this->db->prepare(
            "SELECT `migration`, `batch` FROM `migrations` WHERE `batch` >= ? ORDER BY `id` DESC"
        );
        $stmt->execute([$cutoff]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    private function logMigration(string $filename, int $batch): void
    {
        $stmt = $this->db->prepare("INSERT INTO `migrations` (`migration`, `batch`) VALUES (?, ?)");
        $stmt->execute([$filename, $batch]);
    }

    private function removeMigration(string $filename): void
    {
        $stmt = $this->db->prepare("DELETE FROM `migrations` WHERE `migration` = ?");
        $stmt->execute([$filename]);
    }

    /**
     * Locate the companion _down.sql file for a migration.
     */
    private function getDownFile(string $filename): ?string
    {
        // 0001_create_users_table.sql → 0001_create_users_table_down.sql
        $downName = str_replace('.sql', '_down.sql', $filename);
        $path     = $this->migrationsPath . '/' . $downName;
        return file_exists($path) ? $path : null;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Internal — Dialect Translation
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Translate SQL between MySQL and SQLite dialects.
     */
    private function translateDialect(string $sql): string
    {
        $driver = $this->db->getAttribute(PDO::ATTR_DRIVER_NAME);

        if ($driver === 'sqlite') {
            $sql = preg_replace(
                '/\b(?:BIG|SMALL|MEDIUM|TINY)?INT(?:EGER)?\b(?:\s+UNSIGNED)?\s+AUTO_INCREMENT\s+PRIMARY\s+KEY/i',
                'INTEGER PRIMARY KEY AUTOINCREMENT',
                $sql
            );
            $sql = preg_replace(
                '/\bPRIMARY\s+KEY\s+(?:BIG|SMALL|MEDIUM|TINY)?INT(?:EGER)?\b(?:\s+UNSIGNED)?\s+AUTO_INCREMENT/i',
                'INTEGER PRIMARY KEY AUTOINCREMENT',
                $sql
            );
            $sql = preg_replace('/\s*\bAUTO_INCREMENT\b/i', '', $sql);
            $sql = preg_replace('/ENUM\s*\([^)]+\)/i', 'VARCHAR(50)', $sql);
            $sql = preg_replace('/TINYINT\s+UNSIGNED/i', 'INTEGER', $sql);
            $sql = preg_replace('/\bJSON\b/i', 'TEXT', $sql);
            $sql = preg_replace('/\bUNSIGNED\b/i', '', $sql);
            $sql = preg_replace('/ON\s+UPDATE\s+CURRENT_TIMESTAMP/i', '', $sql);
            $sql = preg_replace('/ENGINE\s*=\s*\w+/i', '', $sql);
            $sql = preg_replace('/DEFAULT\s+CHARSET\s*=\s*\w+/i', '', $sql);
            $sql = preg_replace('/COLLATE\s*=\s*\w+/i', '', $sql);
            $sql = preg_replace('/COMMENT\s+\'[^\']+\'/i', '', $sql);
        } else {
            $sql = str_ireplace('INTEGER PRIMARY KEY AUTOINCREMENT', 'INT AUTO_INCREMENT PRIMARY KEY', $sql);
        }

        return $sql;
    }
}
