<?php

declare(strict_types=1);

namespace Spartan\Tests\Unit;

use PDO;
use Spartan\Database\Migrator;
use Spartan\Database\MysqlDialect;
use Spartan\Database\SqliteDialect;
use Spartan\Tests\TestCase;

final class DatabaseTest extends TestCase
{
    public function test_mysql_dialect_quotes_with_backticks(): void
    {
        $this->assertSame('`name`', (new MysqlDialect())->quoteIdentifier('name'));
        $this->assertSame('`users`', (new MysqlDialect())->quoteTable('users'));
    }

    public function test_sqlite_dialect_quotes_with_double_quotes(): void
    {
        $this->assertSame('"name"', (new SqliteDialect())->quoteIdentifier('name'));
        $this->assertSame('"users"', (new SqliteDialect())->quoteTable('users'));
    }

    public function test_dialects_strip_embedded_quote_characters(): void
    {
        $this->assertSame('`name`', (new MysqlDialect())->quoteIdentifier('na`me'));
        $this->assertSame('"name"', (new SqliteDialect())->quoteIdentifier('na"me'));
    }

    public function test_the_connection_throws_on_error(): void
    {
        $this->assertSame(PDO::ERRMODE_EXCEPTION, $this->app->db->getAttribute(PDO::ATTR_ERRMODE));
    }

    public function test_migrations_are_recorded(): void
    {
        $table = $this->app->db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='migrations'")->fetch();

        $this->assertNotFalse($table);
    }

    public function test_the_framework_schema_is_present(): void
    {
        $names = $this->app->db->query("SELECT name FROM sqlite_master WHERE type='table'")->fetchAll(PDO::FETCH_COLUMN);

        foreach (['jobs', 'users', 'roles', 'permissions'] as $expected) {
            $this->assertContains($expected, $names);
        }
    }

    public function test_migrations_are_idempotent(): void
    {
        $before = (int) $this->app->db->query("SELECT COUNT(*) FROM migrations")->fetchColumn();

        ob_start();
        (new Migrator($this->app->db, __DIR__ . '/../../database/migrations'))->migrate();
        ob_end_clean();

        $this->assertSame($before, (int) $this->app->db->query("SELECT COUNT(*) FROM migrations")->fetchColumn());
    }

    public function test_mysql_ddl_is_translated_for_sqlite(): void
    {
        $ddl = (string) $this->app->db->query("SELECT sql FROM sqlite_master WHERE name='jobs'")->fetchColumn();

        $this->assertStringNotContainsString('AUTO_INCREMENT', $ddl, 'MySQL syntax must not survive translation');
        $this->assertStringNotContainsString('ENUM', $ddl);
        $this->assertStringContainsString('INTEGER PRIMARY KEY AUTOINCREMENT', $ddl);
    }

    public function test_translated_primary_keys_autoincrement(): void
    {
        $this->app->db->prepare("INSERT INTO jobs (event, listener, payload) VALUES (?, ?, ?)")
            ->execute(['t', 'L', '{}']);

        $id = $this->app->db->query("SELECT id FROM jobs ORDER BY id DESC LIMIT 1")->fetchColumn();

        $this->assertNotNull($id, 'a non-rowid primary key would insert NULL ids');
        $this->assertGreaterThan(0, (int) $id);
    }
}
