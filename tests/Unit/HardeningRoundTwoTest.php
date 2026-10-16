<?php

declare(strict_types=1);

namespace Spartan\Tests\Unit;

use PDO;
use Spartan\Application;
use Spartan\ConnectionManager;
use Spartan\Database\Migrator;
use Spartan\JobQueue;
use Spartan\QueryBuilder;
use Spartan\Tests\Fixtures\UserModel;
use Spartan\Tests\TestCase;
use Spartan\View;

/**
 * Regression tests for the second hardening round: migrator, view cache,
 * builder reuse, ORM read/write splitting and the queue's stale window.
 */
final class HardeningRoundTwoTest extends TestCase
{
    // ─── Migrator ────────────────────────────────────────────────────────────

    private function migrator(array $files): array
    {
        $dir = $this->work . '/mig-' . bin2hex(random_bytes(4));
        mkdir($dir);
        foreach ($files as $name => $sql) {
            file_put_contents("{$dir}/{$name}", $sql);
        }
        $pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

        return [new Migrator($pdo, $dir), $pdo];
    }

    private function quietly(callable $fn): void
    {
        ob_start();
        try {
            $fn();
        } finally {
            ob_end_clean();
        }
    }

    public function test_semicolons_inside_strings_comments_and_triggers_do_not_split(): void
    {
        [$migrator, $pdo] = $this->migrator(['0001_t.sql' => <<<'SQL'
            -- a comment; with a semicolon
            CREATE TABLE notes (id INTEGER PRIMARY KEY, body TEXT, n INTEGER DEFAULT 0);
            /* block; comment */
            INSERT INTO notes (body) VALUES ('a;b'), ('it''s; fine');
            CREATE TRIGGER notes_bump AFTER INSERT ON notes BEGIN
                UPDATE notes SET n = n + 1 WHERE id = NEW.id;
                UPDATE notes SET body = body WHERE id = NEW.id;
            END;
            INSERT INTO notes (body) VALUES ('after trigger');
            SQL]);

        $this->quietly(fn() => $migrator->migrate());

        $rows = $pdo->query('SELECT body, n FROM notes ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
        $this->assertSame(['a;b', "it's; fine", 'after trigger'], array_column($rows, 'body'));
        $this->assertSame(1, (int) $rows[2]['n'], 'trigger body ran as one statement');
    }

    public function test_a_failing_sqlite_migration_leaves_nothing_behind(): void
    {
        [$migrator, $pdo] = $this->migrator([
            '0001_bad.sql' => "CREATE TABLE half (id INTEGER); INSERT INTO missing_table VALUES (1);",
        ]);

        try {
            $this->quietly(fn() => $migrator->migrate());
            $this->fail('migration should fail');
        } catch (\PDOException) {
        }

        $tables = $pdo->query("SELECT name FROM sqlite_master WHERE type='table'")->fetchAll(PDO::FETCH_COLUMN);
        $this->assertNotContains('half', $tables, 'the first statement must be rolled back');
        $this->assertSame(0, (int) $pdo->query('SELECT COUNT(*) FROM migrations')->fetchColumn());
    }

    public function test_rollback_finds_the_down_file_for_names_containing_dot_sql(): void
    {
        [$migrator, $pdo] = $this->migrator([
            '0001_add.sql_notes.sql'      => 'CREATE TABLE x (id INTEGER);',
            '0001_add.sql_notes_down.sql' => 'DROP TABLE x;',
        ]);

        $this->quietly(fn() => $migrator->migrate());
        $this->quietly(fn() => $migrator->rollback());

        $tables = $pdo->query("SELECT name FROM sqlite_master WHERE type='table'")->fetchAll(PDO::FETCH_COLUMN);
        $this->assertNotContains('x', $tables);
    }

    // ─── View cache ──────────────────────────────────────────────────────────

    public function test_enabled_view_cache_reuses_compiled_files_and_disabled_recompiles(): void
    {
        $dir = $this->work . '/tpl-cache-' . bin2hex(random_bytes(4));
        mkdir($dir);
        file_put_contents("{$dir}/v.blade.php", 'one');
        $view   = new View($dir);
        $config = $this->app->config['views'] ?? [];

        try {
            $this->app->config['views'] = ['cache_enabled' => true] + $config;
            $this->assertSame('one', $view->renderViewOnly('v'));

            file_put_contents("{$dir}/v.blade.php", 'two');
            touch("{$dir}/v.blade.php", time() + 10);
            $this->assertSame('one', $view->renderViewOnly('v'), 'production mode trusts the compiled file');

            $this->app->config['views'] = ['cache_enabled' => false] + $config;
            $this->assertSame('two', $view->renderViewOnly('v'), 'development mode recompiles changed templates');
        } finally {
            $this->app->config['views'] = $config;
        }
    }

    // ─── Builder reuse ───────────────────────────────────────────────────────

    public function test_first_paginate_and_pluck_leave_the_builder_reusable(): void
    {
        $builder = $this->table('t_users')->orderBy('id');

        $this->assertSame('Ada', $builder->first()['name']);
        $this->assertCount(3, $builder->get(), 'first() no longer leaves LIMIT 1 behind');

        $builder->paginate(1, 2);
        $this->assertCount(3, $builder->get(), 'paginate() no longer leaves LIMIT/OFFSET behind');

        $this->assertSame(['Ada', 'Linus', 'Grace'], $builder->pluck('name'));
        $this->assertArrayHasKey('email', $builder->get()[0], 'pluck() no longer narrows later selects');
    }

    // ─── Read / write splitting ──────────────────────────────────────────────

    /** @return array{0:ConnectionManager,1:string,2:string} */
    private function splitManager(): array
    {
        $primary = $this->work . '/primary-' . bin2hex(random_bytes(4)) . '.sqlite';
        $replica = $this->work . '/replica-' . bin2hex(random_bytes(4)) . '.sqlite';
        foreach ([$primary => 'primary', $replica => 'replica'] as $file => $label) {
            $pdo = new PDO("sqlite:{$file}");
            $pdo->exec("CREATE TABLE t_users (id INTEGER PRIMARY KEY, name TEXT, email TEXT, created_at TEXT, updated_at TEXT);
                        INSERT INTO t_users (name) VALUES ('{$label}')");
        }

        $manager = new ConnectionManager();
        $manager->addConnection('default', ['connection' => 'sqlite', 'database' => $primary]);
        $manager->addReadReplica(['connection' => 'sqlite', 'database' => $replica]);
        $manager->enableSplit(true);

        return [$manager, $primary, $replica];
    }

    public function test_reads_use_the_replica_until_the_request_writes(): void
    {
        [$manager] = $this->splitManager();
        $builder = fn() => new QueryBuilder(
            $manager->connection('write'),
            't_users',
            fn() => $manager->readConnection(),
            fn() => $manager->markWritten()
        );

        $this->assertSame('replica', $builder()->first()['name']);

        $builder()->insert(['name' => 'written']);
        $this->assertSame(['primary', 'written'], $builder()->pluck('name'), 'reads stick to the primary after a write');

        $manager->resetStickiness();
        $this->assertSame('replica', $builder()->first()['name'], 'a new request reads from the replica again');
    }

    public function test_reads_inside_a_transaction_use_the_primary(): void
    {
        [$manager] = $this->splitManager();
        $primary = $manager->connection('write');

        $primary->beginTransaction();
        $this->assertSame($primary, $manager->readConnection());
        $primary->rollBack();

        $this->assertNotSame($primary, $manager->readConnection());
    }

    public function test_models_route_reads_through_the_split(): void
    {
        [$manager] = $this->splitManager();
        $original = $this->app->connections;
        $this->app->connections = $manager;

        try {
            $model = new UserModel();
            (fn() => $this->db = $manager->connection('write'))->call($model);

            $this->assertSame('replica', $model->all()[0]['name']);
            $model->create(['name' => 'new']);
            $this->assertSame(['primary', 'new'], array_column($model->all(), 'name'));
        } finally {
            $this->app->connections = $original;
        }
    }

    public function test_split_disabled_builds_a_plain_builder(): void
    {
        $this->assertFalse($this->app->connections->isSplitEnabled());

        $builder = (new UserModel())->table();
        $read    = (fn() => $this->readConnection)->call($builder);

        $this->assertNull($read, 'no read-routing overhead when splitting is off');
    }

    // ─── Queue stale window ──────────────────────────────────────────────────

    public function test_the_stale_window_is_configurable_for_long_jobs(): void
    {
        $this->app->db->exec("INSERT INTO jobs (event, listener, payload, status, run_at)
                              VALUES ('e', 'L', '[]', 'processing', '" . date('Y-m-d H:i:s', time() - 700) . "')");
        $id = (int) $this->app->db->lastInsertId();

        (new JobQueue($this->app->db, 3600))->processPending();
        $this->assertSame('processing', $this->app->db->query("SELECT status FROM jobs WHERE id = {$id}")->fetchColumn());

        (new JobQueue($this->app->db, 600))->processPending();
        $this->assertNotSame('processing', $this->app->db->query("SELECT status FROM jobs WHERE id = {$id}")->fetchColumn());
    }
}
