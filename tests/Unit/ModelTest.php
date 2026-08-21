<?php

declare(strict_types=1);

namespace Spartan\Tests\Unit;

use Spartan\QueryBuilder;
use Spartan\Tests\Fixtures\TablelessModel;
use Spartan\Tests\Fixtures\UserModel;
use Spartan\Tests\TestCase;

final class ModelTest extends TestCase
{
    private UserModel $model;

    protected function setUp(): void
    {
        parent::setUp();
        $this->model = new UserModel();
    }

    public function test_exposes_its_table(): void
    {
        $this->assertSame('t_users', $this->model->getTable());
    }

    public function test_find_returns_a_row_or_null(): void
    {
        $this->assertSame('Ada', $this->model->find(1)['name']);
        $this->assertNull($this->model->find(999));
    }

    public function test_all_returns_every_row(): void
    {
        $this->assertCount(3, $this->model->all());
    }

    public function test_find_instance_hydrates_a_model(): void
    {
        $user = $this->model->findInstance(1);

        $this->assertInstanceOf(UserModel::class, $user);
        $this->assertSame('Ada', $user->name);
    }

    public function test_find_instance_by_column(): void
    {
        $user = $this->model->findInstanceBy('email', 'linus@example.com');

        $this->assertSame(2, (int) $user->id);
        $this->assertNull($this->model->findInstanceBy('email', 'nobody@example.com'));
    }

    public function test_magic_attribute_access(): void
    {
        $user = $this->model->findInstance(1);
        $user->nickname = 'ada';

        $this->assertTrue(isset($user->nickname));
        $this->assertSame('ada', $user->nickname);

        unset($user->nickname);

        $this->assertFalse(isset($user->nickname));
        $this->assertNull($user->undefined_attribute);
    }

    public function test_to_array_returns_attributes(): void
    {
        $attributes = $this->model->findInstance(1)->toArray();

        $this->assertArrayHasKey('id', $attributes);
        $this->assertArrayHasKey('email', $attributes);
    }

    public function test_create_stamps_timestamps(): void
    {
        $id  = $this->model->create(['name' => 'Stamped', 'email' => 'stamp@example.com']);
        $row = $this->model->find((int) $id);

        $this->assertNotEmpty($row['created_at']);
        $this->assertNotEmpty($row['updated_at']);
    }

    public function test_save_updates_the_row_and_the_timestamp(): void
    {
        $id = (int) $this->model->create(['name' => 'Stamped', 'email' => 'stamp2@example.com']);
        $before = $this->model->find($id)['updated_at'];

        $this->model->save($id, ['name' => 'Renamed', 'updated_at' => '2030-01-01 00:00:00']);
        $after = $this->model->find($id);

        $this->assertSame('Renamed', $after['name']);
        $this->assertNotSame($before, $after['updated_at']);
    }

    public function test_table_returns_a_builder_for_this_or_another_table(): void
    {
        $this->assertInstanceOf(QueryBuilder::class, $this->model->table());
        $this->assertSame(3, $this->model->table('t_posts')->count());
    }

    public function test_a_model_without_a_table_is_rejected(): void
    {
        $this->expectException(\RuntimeException::class);
        (new TablelessModel())->table();
    }

    public function test_transaction_commits_on_success(): void
    {
        $before = $this->model->table()->count();

        $this->model->transaction(fn($model) => $model->create(['name' => 'Tx', 'email' => 'tx@example.com']));

        $this->assertSame($before + 1, $this->model->table()->count());
    }

    public function test_transaction_rolls_back_on_failure(): void
    {
        $before = $this->model->table()->count();

        try {
            $this->model->transaction(function ($model) {
                $model->create(['name' => 'Doomed', 'email' => 'doomed@example.com']);
                throw new \RuntimeException('boom');
            });
        } catch (\RuntimeException) {
            // expected
        }

        $this->assertSame($before, $this->model->table()->count(), 'the insert must be rolled back');
    }

    public function test_nested_transactions_join_the_outer_one(): void
    {
        $result = $this->model->transaction(fn($model) => $model->transaction(fn() => 'inner-ok'));

        $this->assertSame('inner-ok', $result);
        $this->assertFalse($this->app->db->inTransaction(), 'the outer transaction must be committed exactly once');
    }

    public function test_a_nested_failure_unwinds_the_whole_transaction(): void
    {
        $before = $this->model->table()->count();

        try {
            $this->model->transaction(function ($model) {
                $model->create(['name' => 'N1', 'email' => 'n1@example.com']);
                $model->transaction(function () {
                    throw new \RuntimeException('inner boom');
                });
            });
        } catch (\RuntimeException) {
            // expected
        }

        $this->assertSame($before, $this->model->table()->count());
        $this->assertFalse($this->app->db->inTransaction());
    }

    public function test_transaction_helpers_are_exposed(): void
    {
        $this->assertTrue($this->model->beginTransaction());
        $this->assertTrue($this->model->rollBack());
    }
}
