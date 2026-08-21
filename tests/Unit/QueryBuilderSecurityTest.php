<?php

declare(strict_types=1);

namespace Spartan\Tests\Unit;

use Spartan\Tests\TestCase;

/**
 * The query builder is the framework's main promise: no raw SQL reaches the
 * driver. These tests pin that promise down for values, operators and
 * identifiers alike.
 */
final class QueryBuilderSecurityTest extends TestCase
{
    public function test_values_are_bound_not_interpolated(): void
    {
        $rows = $this->table('t_users')->where('name', "Ada' OR '1'='1")->get();

        $this->assertCount(0, $rows, 'the injection string must be treated as data');
    }

    public function test_a_quote_in_a_value_cannot_break_out(): void
    {
        $rows = $this->table('t_users')->where('email', "x'; DROP TABLE t_users; --")->get();

        $this->assertCount(0, $rows);
        $this->assertSame(3, $this->table('t_users')->count(), 'the table must survive');
    }

    /**
     * @dataProvider injectedOperators
     */
    public function test_injected_operators_are_rejected(string $operator): void
    {
        $this->expectException(\RuntimeException::class);
        $this->table('t_users')->where('id', 1, $operator);
    }

    public static function injectedOperators(): array
    {
        return [
            ['= 1 OR 1=1 --'],
            ['; DROP TABLE t_users'],
            ['UNION SELECT'],
            ['>= 0 OR TRUE'],
        ];
    }

    public function test_injected_having_operator_is_rejected(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->table('t_posts')->having('cnt', 1, '> 0 UNION SELECT 1');
    }

    /**
     * @dataProvider legitimateOperators
     */
    public function test_legitimate_operators_are_accepted(string $operator, mixed $value): void
    {
        $this->table('t_users')->where('score', $value, $operator)->get();

        $this->expectNotToPerformAssertions();
    }

    public static function legitimateOperators(): array
    {
        return [
            ['=', 1], ['!=', 1], ['<>', 1], ['<', 1], ['>', 1], ['<=', 1], ['>=', 1],
            ['LIKE', '%a%'], ['NOT LIKE', '%a%'], ['IN', [1, 2]], ['NOT IN', [1, 2]],
            ['like', '%a%'], ['not  in', [1, 2]],
        ];
    }

    /**
     * @dataProvider injectedColumns
     */
    public function test_injected_column_names_are_rejected(string $column, string $method): void
    {
        $this->expectException(\RuntimeException::class);

        $builder = $this->table('t_users');
        match ($method) {
            'select'  => $builder->select($column)->get(),
            'where'   => $builder->where($column, 1)->get(),
            'orderBy' => $builder->orderBy($column)->get(),
            'groupBy' => $builder->select('id')->groupBy($column)->get(),
        };
    }

    public static function injectedColumns(): array
    {
        return [
            'select subquery'   => ['(SELECT email FROM t_users LIMIT 1)', 'select'],
            'select breakout'   => ['id) FROM t_users WHERE 1=1 --', 'select'],
            'select backtick'   => ['name`; DROP TABLE t_users; --', 'select'],
            'where breakout'    => ['id = 1 OR 1=1 --', 'where'],
            'order by stacked'  => ['id; DROP TABLE t_users', 'orderBy'],
            'group by union'    => ['user_id) UNION SELECT 1 --', 'groupBy'],
        ];
    }

    public function test_aggregate_expressions_still_compile(): void
    {
        $row = $this->table('t_users')->select('COUNT(id) as total', 'MAX(score) as top', 'AVG(score) as mean')->first();

        $this->assertSame(3, (int) $row['total']);
        $this->assertSame(90, (int) $row['top']);
    }

    public function test_count_distinct_compiles(): void
    {
        $row = $this->table('t_posts')->select('COUNT(DISTINCT user_id) as c')->first();

        $this->assertSame(2, (int) $row['c']);
    }

    public function test_qualified_and_aliased_columns_compile(): void
    {
        $row = $this->table('t_users')->select('t_users.name as who')->where('id', 1)->first();

        $this->assertSame('Ada', $row['who']);
    }

    public function test_the_fixture_table_survives_every_attempt(): void
    {
        $this->assertSame(3, $this->table('t_users')->count());
    }
}
