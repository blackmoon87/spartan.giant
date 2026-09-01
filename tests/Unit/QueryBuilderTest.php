<?php

declare(strict_types=1);

namespace Spartan\Tests\Unit;

use Spartan\QueryBuilder;
use Spartan\Tests\TestCase;

final class QueryBuilderTest extends TestCase
{
    public function test_get_returns_every_row(): void
    {
        $this->assertCount(3, $this->table('t_users')->get());
    }

    public function test_select_limits_the_columns(): void
    {
        $row = $this->table('t_users')->select('id', 'name')->first();

        $this->assertSame(['id', 'name'], array_keys($row));
    }

    public function test_select_splits_a_comma_separated_string(): void
    {
        $row = $this->table('t_users')->select('id, name, email')->first();

        $this->assertCount(3, $row);
    }

    public function test_where_equality(): void
    {
        $this->assertCount(1, $this->table('t_users')->where('role', 'admin')->get());
    }

    public function test_where_with_a_comparison_operator(): void
    {
        $this->assertCount(2, $this->table('t_users')->where('score', 60, '>')->get());
    }

    public function test_where_like(): void
    {
        $this->assertCount(3, $this->table('t_users')->where('email', '%example.com', 'LIKE')->get());
    }

    public function test_where_in_and_not_in(): void
    {
        // Existing behavior preserved
        $this->assertCount(2, $this->table('t_users')->where('id', [1, 3])->get());
        $this->assertCount(1, $this->table('t_users')->where('id', [1, 3], 'NOT IN')->get());

        // Dedicated helper methods
        $this->assertCount(2, $this->table('t_users')->whereIn('id', [1, 3])->get());
        $this->assertCount(1, $this->table('t_users')->whereNotIn('id', [1, 3])->get());

        // OR variants
        $this->assertCount(3, $this->table('t_users')->where('id', 1)->orWhereIn('id', [2, 3])->get());
        $this->assertCount(2, $this->table('t_users')->where('id', 1)->orWhereNotIn('id', [1, 2])->get());

        // Empty array safe handling (no SQL syntax crash)
        $this->assertCount(0, $this->table('t_users')->whereIn('id', [])->get());
        $this->assertCount(3, $this->table('t_users')->whereNotIn('id', [])->get());
        $this->assertCount(0, $this->table('t_users')->where('id', [])->get());
        $this->assertCount(3, $this->table('t_users')->where('id', [], 'NOT IN')->get());
    }

    public function test_chained_wheres_are_anded(): void
    {
        $this->assertCount(1, $this->table('t_users')->where('active', 1)->where('role', 'user')->get());
    }

    public function test_or_where_widens_the_result(): void
    {
        $this->assertCount(2, $this->table('t_users')->where('role', 'admin')->orWhere('score', 60, '<')->get());
    }

    public function test_first_returns_one_row_or_null(): void
    {
        $this->assertSame('Ada', $this->table('t_users')->where('id', 1)->first()['name']);
        $this->assertNull($this->table('t_users')->where('id', 999)->first());
    }

    public function test_find_by_primary_key(): void
    {
        $this->assertSame('Linus', $this->table('t_users')->find(2)['name']);
    }

    public function test_count_and_exists(): void
    {
        $this->assertSame(3, $this->table('t_users')->count());
        $this->assertSame(2, $this->table('t_users')->where('active', 1)->count());
        $this->assertTrue($this->table('t_users')->where('id', 1)->exists());
        $this->assertFalse($this->table('t_users')->where('id', 999)->exists());
    }

    public function test_order_by_ascending_and_descending(): void
    {
        $this->assertSame([55, 70, 90], array_map('intval', array_column($this->table('t_users')->orderBy('score')->get(), 'score')));
        $this->assertSame([90, 70, 55], array_map('intval', array_column($this->table('t_users')->orderBy('score', 'DESC')->get(), 'score')));
    }

    public function test_an_invalid_sort_direction_defaults_to_ascending(): void
    {
        $rows = $this->table('t_users')->orderBy('score', 'SNEAKY; DROP TABLE')->get();

        $this->assertSame([55, 70, 90], array_map('intval', array_column($rows, 'score')));
    }

    public function test_limit_and_offset(): void
    {
        $rows = $this->table('t_users')->orderBy('id')->limit(1)->offset(1)->get();

        $this->assertCount(1, $rows);
        $this->assertSame(2, (int) $rows[0]['id']);
    }

    public function test_inner_join_three_and_four_argument_forms(): void
    {
        $three = $this->table('t_posts')->join('t_users', 't_users.id', 't_posts.user_id')->get();
        $four  = $this->table('t_posts')->join('t_users', 't_users.id', '=', 't_posts.user_id')->get();

        $this->assertCount(3, $three);
        $this->assertCount(3, $four);
    }

    public function test_left_join_keeps_unmatched_rows(): void
    {
        $rows = $this->table('t_users')->leftJoin('t_posts', 't_posts.user_id', 't_users.id')->get();

        $this->assertCount(4, $rows, '3 posts plus the postless user');
    }

    public function test_group_by_aggregates(): void
    {
        $rows = $this->table('t_posts')->select('user_id', 'COUNT(id) as cnt')->groupBy('user_id')->orderBy('user_id')->get();

        $this->assertSame(2, (int) $rows[0]['cnt']);
        $this->assertSame(1, (int) $rows[1]['cnt']);
    }

    public function test_having_filters_groups(): void
    {
        $rows = $this->table('t_posts')->select('user_id', 'COUNT(id) as cnt')->groupBy('user_id')->having('cnt', 1, '>')->get();

        $this->assertCount(1, $rows, 'integers must bind as integers for the comparison to work');
        $this->assertSame(1, (int) $rows[0]['user_id']);
    }

    public function test_count_honours_joins(): void
    {
        $count = $this->table('t_posts')
            ->join('t_users', 't_users.id', 't_posts.user_id')
            ->where('t_users.role', 'admin')
            ->count();

        $this->assertSame(2, $count, 'joins must not be dropped from the count query');
    }

    public function test_count_honours_group_by(): void
    {
        $this->assertSame(2, $this->table('t_posts')->groupBy('user_id')->count(), 'groups are counted, not rows');
    }

    public function test_paginate_first_page(): void
    {
        $page = $this->table('t_users')->orderBy('id')->paginate(2, 1);

        $this->assertSame(3, $page['total']);
        $this->assertSame(2, $page['last_page']);
        $this->assertSame(1, $page['current_page']);
        $this->assertCount(2, $page['data']);
    }

    public function test_paginate_last_page_remainder(): void
    {
        $page = $this->table('t_users')->orderBy('id')->paginate(2, 2);

        $this->assertCount(1, $page['data']);
        $this->assertSame(3, (int) $page['data'][0]['id']);
    }

    public function test_paginate_clamps_a_page_below_one(): void
    {
        $this->assertSame(1, $this->table('t_users')->paginate(2, 0)['current_page']);
    }

    public function test_paginate_rejects_a_zero_page_size(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->table('t_users')->paginate(0);
    }

    public function test_paginate_counts_groups_when_grouped(): void
    {
        $page = $this->table('t_posts')->select('user_id')->groupBy('user_id')->paginate(10, 1);

        $this->assertSame(2, $page['total']);
    }

    public function test_insert_update_and_delete(): void
    {
        $id = $this->table('t_users')->insert(['name' => 'Temp', 'email' => 'temp@example.com', 'score' => 10]);
        $this->assertGreaterThan(0, (int) $id);

        $updated = $this->table('t_users')->where('email', 'temp@example.com')->update(['score' => 11]);
        $this->assertSame(1, $updated);
        $this->assertSame(11, (int) $this->table('t_users')->where('email', 'temp@example.com')->first()['score']);

        $deleted = $this->table('t_users')->where('email', 'temp@example.com')->delete();
        $this->assertSame(1, $deleted);
        $this->assertSame(3, $this->table('t_users')->count());
    }

    public function test_update_without_a_where_is_blocked(): void
    {
        $this->expectException(\LogicException::class);
        $this->table('t_users')->update(['score' => 0]);
    }

    public function test_delete_without_a_where_is_blocked(): void
    {
        $this->expectException(\LogicException::class);
        $this->table('t_users')->delete();
    }

    public function test_insert_requires_columns(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->table('t_users')->insert([]);
    }

    public function test_update_requires_columns(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->table('t_users')->where('id', 1)->update([]);
    }

    public function test_truncate_is_the_explicit_clear_all(): void
    {
        $this->app->db->exec("CREATE TABLE IF NOT EXISTS t_tmp (id INTEGER PRIMARY KEY, v TEXT)");
        $this->app->db->exec("DELETE FROM t_tmp; INSERT INTO t_tmp (v) VALUES ('a'),('b')");

        $this->assertSame(2, $this->table('t_tmp')->truncate());
        $this->assertSame(0, $this->table('t_tmp')->count());
    }

    public function test_an_empty_table_name_is_rejected(): void
    {
        $this->expectException(\RuntimeException::class);
        new QueryBuilder($this->app->db, '');
    }

    public function test_null_values_bind_as_null(): void
    {
        $this->table('t_users')->insert(['name' => 'NullScore', 'email' => 'null@example.com', 'score' => null]);
        $row = $this->table('t_users')->where('email', 'null@example.com')->first();

        $this->assertNull($row['score']);
    }

    public function test_where_null_and_where_not_null(): void
    {
        $this->table('t_users')->insert(['name' => 'NoScore', 'email' => 'noscore@example.com', 'score' => null]);

        $this->assertCount(1, $this->table('t_users')->whereNull('score')->get());
        $this->assertCount(3, $this->table('t_users')->whereNotNull('score')->get());

        // where('col', null) auto-conversion to IS NULL
        $this->assertCount(1, $this->table('t_users')->where('score', null)->get());
        $this->assertCount(3, $this->table('t_users')->where('score', null, '!=')->get());

        // OR variants
        $this->assertCount(2, $this->table('t_users')->where('id', 1)->orWhereNull('score')->get());
        $this->assertCount(3, $this->table('t_users')->where('id', 1)->orWhereNotNull('score')->get());
    }

    public function test_where_between_and_where_not_between(): void
    {
        $this->assertCount(2, $this->table('t_users')->whereBetween('score', [50, 75])->get());
        $this->assertCount(1, $this->table('t_users')->whereNotBetween('score', [50, 75])->get());
        $this->assertCount(3, $this->table('t_users')->where('id', 1)->orWhereBetween('score', [50, 80])->get());
    }

    public function test_nested_closure_where_grouping(): void
    {
        $rows = $this->table('t_users')
            ->where('active', 1)
            ->where(function (QueryBuilder $q) {
                $q->where('role', 'admin')->orWhere('score', 80, '>');
            })
            ->get();

        $this->assertCount(1, $rows);
        $this->assertSame('Ada', $rows[0]['name']);
    }

    public function test_multi_order_by(): void
    {
        $rows = $this->table('t_users')
            ->orderBy('active', 'DESC')
            ->orderBy('score', 'ASC')
            ->get();

        $this->assertCount(3, $rows);
    }

    public function test_pluck(): void
    {
        $names = $this->table('t_users')->orderBy('id')->pluck('name');
        $this->assertSame(['Ada', 'Linus', 'Grace'], $names);

        $keyed = $this->table('t_users')->orderBy('id')->pluck('name', 'id');
        $this->assertSame(['1' => 'Ada', '2' => 'Linus', '3' => 'Grace'], $keyed);
    }

    public function test_chunk_and_cursor(): void
    {
        $collected = [];
        $this->table('t_users')->orderBy('id')->chunk(2, function (array $rows, int $page) use (&$collected) {
            foreach ($rows as $row) {
                $collected[] = $row['name'];
            }
        });
        $this->assertSame(['Ada', 'Linus', 'Grace'], $collected);

        $streamed = [];
        foreach ($this->table('t_users')->orderBy('id')->cursor() as $row) {
            $streamed[] = $row['name'];
        }
        $this->assertSame(['Ada', 'Linus', 'Grace'], $streamed);
    }

    public function test_increment_and_decrement(): void
    {
        $this->table('t_users')->where('id', 1)->increment('score', 5);
        $this->assertSame(95, (int) $this->table('t_users')->where('id', 1)->first()['score']);

        $this->table('t_users')->where('id', 1)->decrement('score', 10);
        $this->assertSame(85, (int) $this->table('t_users')->where('id', 1)->first()['score']);
    }
}
