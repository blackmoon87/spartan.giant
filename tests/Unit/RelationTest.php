<?php

declare(strict_types=1);

namespace Spartan\Tests\Unit;

use Spartan\Tests\Fixtures\PostModel;
use Spartan\Tests\Fixtures\UserModel;
use Spartan\Tests\TestCase;

final class RelationTest extends TestCase
{
    public function test_has_many_for_a_single_parent(): void
    {
        $users = new UserModel();
        $posts = $users->posts()->for($users->findInstance(1));

        $this->assertCount(2, $posts);
    }

    public function test_has_many_is_empty_for_a_childless_parent(): void
    {
        $users = new UserModel();

        $this->assertSame([], $users->posts()->for($users->findInstance(3)));
    }

    public function test_has_one_returns_a_single_record(): void
    {
        $users = new UserModel();
        $post  = $users->latestPost()->for($users->findInstance(1));

        $this->assertIsArray($post);
        $this->assertArrayHasKey('title', $post);
    }

    public function test_belongs_to_resolves_the_owner(): void
    {
        $posts = new PostModel();
        $owner = $posts->author()->for($posts->findInstance(1));

        $this->assertSame('Ada', $owner['name']);
    }

    public function test_load_for_eager_loads_a_collection(): void
    {
        $users = new UserModel();
        $rows  = $users->posts()->loadFor($users->all(), 'posts');

        $this->assertSame([2, 1, 0], array_map(fn($row) => count($row['posts'] ?? []), $rows));
    }

    public function test_load_for_works_in_the_belongs_to_direction(): void
    {
        $posts = new PostModel();
        $rows  = $posts->author()->loadFor($posts->all(), 'author');

        $this->assertSame('Ada', $rows[0]['author']['name']);
    }

    public function test_eager_loading_issues_a_constant_number_of_queries(): void
    {
        $users = new UserModel();
        $all   = $users->all();

        $started = microtime(true);
        $users->posts()->loadFor($all, 'posts');
        $elapsed = microtime(true) - $started;

        $this->assertLessThan(0.5, $elapsed, 'eager loading must not degrade into N+1 queries');
    }

    public function test_a_relation_to_an_unknown_class_is_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new UserModel())->brokenRelation();
    }
}
