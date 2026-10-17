<?php

declare(strict_types=1);

namespace Spartan\Tests\Unit;

use Spartan\Gate;
use Spartan\Model;
use Spartan\QueryBuilder;
use Spartan\Tests\Fixtures\PostModel;
use Spartan\Tests\Fixtures\UserModel;
use Spartan\Tests\TestCase;
use Spartan\Validator;
use Spartan\View;

/**
 * Keeps AGENTS.md and docs/ai/*.md honest: every framework method they name
 * must exist, and the recipes they teach must run as written.
 */
final class AgentDocsTest extends TestCase
{
    private const DOCS = ['AGENTS.md', 'docs/ai/database.md', 'docs/ai/http.md', 'docs/ai/views.md',
                          'docs/ai/auth.md', 'docs/ai/validation.md', 'docs/ai/services.md'];

    /**
     * Methods the docs define in their own example application code (models,
     * controllers, policies…) or call on PHP built-ins — not framework API.
     */
    private const APP_DEFINED = [
        'published', 'posts', 'profile', 'author', 'publish', 'handle', 'update', 'index', 'show',
        'store', 'register', 'showRegister', 'showLogin', 'login', 'logout', 'patch', 'destroy',
        'file',            // finfo::file()
    ];

    /** Laravel APIs the docs cite only to say Spartan does it differently. */
    private const LARAVEL_REFERENCES = ['attempt'];

    public function test_every_method_named_in_the_docs_exists(): void
    {
        $framework = '';
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(__DIR__ . '/../../framework/src')) as $f) {
            if ($f->isFile() && str_ends_with($f->getFilename(), '.php')) {
                $framework .= file_get_contents($f->getPathname());
            }
        }

        $missing = [];
        foreach (self::DOCS as $doc) {
            $text = (string) file_get_contents(__DIR__ . '/../../' . $doc);
            preg_match_all('/(?:->|::)([a-zA-Z_][a-zA-Z0-9_]*)\(/', $text, $m);
            foreach (array_unique($m[1]) as $method) {
                if (in_array($method, self::APP_DEFINED, true) || in_array($method, self::LARAVEL_REFERENCES, true)) {
                    continue;
                }
                if (!preg_match('/function\s+' . preg_quote($method, '/') . '\s*\(/', $framework)) {
                    $missing[] = "{$doc}: {$method}()";
                }
            }
        }

        $this->assertSame([], $missing, 'Docs name methods the framework does not have');
    }

    public function test_every_helper_named_in_the_docs_exists(): void
    {
        foreach (['old', 'errors', 'url', 'asset', 'trans', '__', 'current_locale', 'is_rtl', 'auth', 'config', 'env'] as $fn) {
            $this->assertTrue(function_exists($fn), "helper {$fn}() is documented but missing");
        }
    }

    // ─── database.md ─────────────────────────────────────────────────────────

    public function test_database_guide_query_examples_run(): void
    {
        $q = fn() => $this->table('t_users');

        $this->assertCount(2, $q()->where('role', ['admin', 'user'])->where('active', 1)->get());
        $this->assertCount(0, $q()->where('role', [])->get(), 'empty IN list → no rows');
        $this->assertSame(2, $q()->where('score', 60, '>=')->count());
        $this->assertSame([1 => 'Ada', 2 => 'Linus', 3 => 'Grace'], $q()->orderBy('id')->pluck('name', 'id'));
        $this->assertSame(['data', 'total', 'per_page', 'current_page', 'last_page'], array_keys($q()->paginate(2, 1)));
        $this->assertCount(2, $q()->where(function (QueryBuilder $w) {
            $w->where('role', 'admin')->orWhere('name', 'Linus');
        })->get());
        $this->assertSame('admin', $q()->select("COALESCE(role, 'none') as r")->where('id', 1)->first()['r']);

        $pages = 0;
        $q()->orderBy('id')->chunk(1, function (array $rows, int $page) use (&$pages) {
            $pages = $page;
            return $page < 2 ? null : false;      // stop after the second chunk
        });
        $this->assertSame(2, $pages);

        $this->assertSame(1, $q()->where('id', 1)->increment('score', 5, ['role' => 'admin']));
        $this->expectException(\LogicException::class);
        $q()->update(['score' => 0]);              // no where() → refused
    }

    public function test_database_guide_relation_examples_run(): void
    {
        $user  = (new UserModel())->find(1);
        $posts = (new UserModel())->posts()->for($user);
        $this->assertCount(2, $posts);

        $users = (new UserModel())->posts()->loadFor((new UserModel())->all(), 'posts');
        $this->assertCount(2, $users[0]['posts']);

        $this->assertSame('Ada', (new PostModel())->author()->for((new PostModel())->find(1))['name']);
    }

    public function test_database_guide_transaction_example_rolls_back(): void
    {
        try {
            (new UserModel())->transaction(function (UserModel $users) {
                $users->create(['name' => 'Temp', 'email' => 't@x.io']);
                throw new \RuntimeException('fail');
            });
        } catch (\RuntimeException) {
        }

        $this->assertSame(0, $this->table('t_users')->where('email', 't@x.io')->count());
    }

    // ─── auth.md ─────────────────────────────────────────────────────────────

    public function test_auth_guide_register_login_and_roles_recipe_runs(): void
    {
        $db = $this->app->db;
        $db->exec("DELETE FROM user_roles; DELETE FROM role_permissions; DELETE FROM roles; DELETE FROM permissions; DELETE FROM users;");
        $db->exec("INSERT INTO roles (id, name, slug) VALUES (1, 'Admin', 'admin'), (2, 'Member', 'member');
                   INSERT INTO permissions (id, name, slug) VALUES (1, 'Manage users', 'manage_users'), (2, 'Publish posts', 'publish_posts');
                   INSERT INTO role_permissions (role_id, permission_id) VALUES (1, 1), (1, 2), (2, 2);");

        // register
        $id = (new DocsUser())->create([
            'name'     => 'Ada',
            'email'    => 'ada@example.com',
            'password' => password_hash('secret-pass', PASSWORD_DEFAULT),
        ]);
        (new DocsUser())->findInstance($id)?->assignRole('member');

        // login
        $user = (new DocsUser())->findInstanceBy('email', 'ada@example.com');
        $this->assertNotNull($user);
        $this->assertTrue(password_verify('secret-pass', (string) $user->password));
        $this->assertFalse(password_verify('wrong', (string) $user->password));
        $this->assertNotNull($user->created_at, '$timestamps = true fills created_at');

        // roles & permissions
        $this->assertTrue($user->hasRole('admin', 'member'));
        $this->assertFalse($user->hasRole('admin'));
        $this->assertTrue($user->hasPermission('publish_posts'));
        $this->assertFalse($user->hasPermission('manage_users'));

        $this->expectException(\InvalidArgumentException::class);
        $user->assignRole('no-such-role');
    }

    public function test_auth_guide_gates_and_policies_run(): void
    {
        $owner = (object) ['id' => 7];

        Gate::define('edit-post', function (?object $user, array $post): bool {
            return $user !== null && (int) $post['user_id'] === (int) $user->id;
        });
        $this->assertTrue(Gate::forUser($owner)->allows('edit-post', ['user_id' => 7]));
        $this->assertTrue(Gate::forUser(null)->denies('edit-post', ['user_id' => 7]));

        Gate::policy(DocsPost::class, DocsPostPolicy::class);
        $post = new DocsPost();
        $post->user_id = 7;
        $this->assertTrue(Gate::forUser($owner)->allows('update', $post));
        $this->assertFalse(Gate::forUser((object) ['id' => 8])->allows('update', $post));
    }

    // ─── validation.md ───────────────────────────────────────────────────────

    public function test_validation_guide_examples_run(): void
    {
        $v = new Validator();
        $v->setDb($this->app->db);

        $this->assertFalse($v->validate(['email' => 'ada@example.com'], ['email' => 'required|email|unique:t_users,email']));
        $this->assertTrue($v->validate(['email' => 'ada@example.com'], ['email' => 'required|email|unique:t_users,email,1']));
        $this->assertTrue($v->validate(['password' => 'longenough', 'password_confirmation' => 'longenough'], ['password' => 'required|string|min:8|confirmed']));
        $this->assertTrue($v->validate(['age' => ''], ['age' => 'nullable|integer|min:18|max:120']));
        $this->assertFalse($v->validate(['age' => '12'], ['age' => 'nullable|integer|min:18|max:120']));
        $this->assertTrue($v->validate(['code' => 'AB-1234'], ['code' => ['required', 'regex:/^(AB|CD)-\d{4}$/']]));
        $this->assertSame('The email field must be a valid email address.',
            ($v->validate(['email' => 'nope'], ['email' => 'email']) ? null : $v->error('email')));
    }

    // ─── views.md ────────────────────────────────────────────────────────────

    public function test_views_guide_layout_form_and_directives_render(): void
    {
        $dir = $this->work . '/docs-views-' . bin2hex(random_bytes(3));
        mkdir($dir . '/layouts', 0777, true);
        mkdir($dir . '/posts', 0777, true);
        file_put_contents("$dir/layouts/app.blade.php",
            '<title>@yield("title")</title><meta content="{{ $this->csrfTokenValue() }}">'
            . '@flash("success")<div class="alert">{{ $flashMsg }}</div>@endflash@yield("content")');
        file_put_contents("$dir/posts/card.blade.php", '<li>{{ $post["title"] }}</li>');
        file_put_contents("$dir/posts/index.blade.php", implode("\n", [
            "@extends('layouts.app')",
            "@section('title', 'All posts')",
            "@section('content')",
            "@foreach(\$posts as \$post)@include('posts.card', ['post' => \$post])@endforeach",
            "@empty(\$none)<p>none</p>@endempty",
            "<form>@csrf @method('PUT')<input value=\"{{ old('title') }}\">@if(errors('title'))<p class=\"error\">{{ errors('title') }}</p>@endif",
            "<option @selected(old('status') === 'draft')>Draft</option></form>",
            "@auth in @endauth @guest out @endguest @@if {!! '<b>raw</b>' !!} @lang('app.missing_key')",
            "@endsection",
        ]));

        $this->app->session->setFlash('success', 'Saved.');
        $this->app->session->setFlash('old_input', ['title' => 'Hello <b>', 'status' => 'draft']);
        $this->app->session->setFlash('validation_errors', ['title' => 'The title field is required.']);

        $html = (new View($dir))->render('posts.index', [
            'posts' => [['title' => 'One'], ['title' => 'Two']], 'none' => [],
        ]);
        $this->app->session->remove('flash_messages');

        $this->assertStringContainsString('<title>All posts</title>', $html);
        $this->assertStringContainsString('<div class="alert">Saved.</div>', $html);
        $this->assertStringContainsString('<li>One</li><li>Two</li>', $html);
        $this->assertStringContainsString('<p>none</p>', $html);
        $this->assertStringContainsString('name="_csrf"', $html);
        $this->assertStringContainsString('name="_method" value="PUT"', $html);
        $this->assertStringContainsString('value="Hello &lt;b&gt;"', $html, 'old() output is escaped');
        $this->assertStringContainsString('<p class="error">The title field is required.</p>', $html);
        $this->assertStringContainsString('selected="selected"', $html);
        $this->assertStringContainsString('out', $html);
        $this->assertStringContainsString('@if', $html);
        $this->assertStringContainsString('<b>raw</b>', $html);
        $this->assertStringContainsString('app.missing_key', $html, 'missing translation prints the key');
        $this->assertDoesNotMatchRegularExpression('/@(section|yield|foreach|include|csrf|method|selected|auth|guest|lang|flash)\b/', $html);
    }

    public function test_errors_helper_matches_the_docs(): void
    {
        $this->assertSame([], errors());
        $this->app->session->setFlash('validation_errors', ['email' => 'Bad email.']);
        $this->assertSame(['email' => 'Bad email.'], errors());
        $this->assertSame('Bad email.', errors('email'));
        $this->assertNull(errors('name'));
        $this->app->session->remove('flash_messages');
    }
}

/** User model exactly as in docs/ai/auth.md. */
class DocsUser extends Model
{
    use \Spartan\Traits\HasAuthorization;

    protected string $table = 'users';
    protected bool $timestamps = true;
}

class DocsPost extends Model
{
    protected string $table = 't_posts';
}

/** Policy exactly as in docs/ai/auth.md. */
class DocsPostPolicy
{
    public function update(?object $user, DocsPost $post): bool
    {
        return $user !== null && (int) $post->user_id === (int) $user->id;
    }
}
