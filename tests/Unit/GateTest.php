<?php

declare(strict_types=1);

namespace Spartan\Tests\Unit;

use Spartan\Gate;
use Spartan\Tests\Fixtures\Article;
use Spartan\Tests\Fixtures\ArticlePolicy;
use Spartan\Tests\TestCase;

final class GateTest extends TestCase
{
    public function test_a_defined_ability_can_allow(): void
    {
        Gate::define('ship-it', fn($user) => true);

        $this->assertTrue(Gate::check('ship-it'));
        $this->assertTrue(Gate::allows('ship-it'));
        $this->assertFalse(Gate::denies('ship-it'));
    }

    public function test_a_defined_ability_can_deny(): void
    {
        Gate::define('nope', fn($user) => false);

        $this->assertFalse(Gate::check('nope'));
        $this->assertTrue(Gate::denies('nope'));
    }

    public function test_an_undefined_ability_fails_closed(): void
    {
        $this->assertFalse(Gate::check('never-defined'), 'authorization must default to deny');
    }

    public function test_abilities_receive_arguments(): void
    {
        Gate::define('own-post', fn($user, $post) => ($post->user_id ?? null) === 7);

        $this->assertTrue(Gate::check('own-post', (object) ['user_id' => 7]));
        $this->assertFalse(Gate::check('own-post', (object) ['user_id' => 8]));
    }

    public function test_policies_dispatch_by_model_class(): void
    {
        Gate::policy(Article::class, ArticlePolicy::class);

        $this->assertTrue(Gate::check('update', new Article(5)));
        $this->assertFalse(Gate::check('update', new Article(6)));
    }

    public function test_inspect_evaluates_an_explicit_user(): void
    {
        Gate::define('is-admin', fn($user) => ($user->role ?? null) === 'admin');

        $this->assertTrue(Gate::inspect((object) ['role' => 'admin'], 'is-admin'));
        $this->assertFalse(Gate::inspect((object) ['role' => 'user'], 'is-admin'));
    }

    public function test_for_user_returns_an_evaluator(): void
    {
        Gate::define('is-admin', fn($user) => ($user->role ?? null) === 'admin');

        $evaluator = Gate::forUser((object) ['role' => 'admin']);

        $this->assertTrue($evaluator->allows('is-admin'));
        $this->assertFalse($evaluator->denies('is-admin'));
    }

    public function test_resolve_user_prefers_the_container_identity(): void
    {
        $this->app->container->instance('auth_user', (object) ['id' => 55]);

        $this->assertSame(55, Gate::resolveUser()->id);
    }

    public function test_resolve_user_falls_back_to_the_auth_service(): void
    {
        $this->app->session->set('user_id', 2);
        $this->app->auth->forgetUser();

        $this->assertSame('linus@example.com', Gate::resolveUser()->email);
    }
}
