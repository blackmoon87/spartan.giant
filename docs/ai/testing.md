# Testing — PHPUnit tests for your application

Source: `phpunit.xml`, `tests/bootstrap.php`, `tests/TestCase.php`.

## How the test environment works

- `vendor/bin/phpunit` runs every `tests/Unit/*Test.php`.
- `tests/bootstrap.php` boots **one** Application for the whole run, with a
  throwaway SQLite database in the system temp folder, and runs every file in
  `database/migrations/` — your app's tables exist in tests automatically.
- The test app registers **no routes and no global middleware** (CSRF is off);
  each test registers what it needs.
- `SPARTAN_TESTING` is defined, so a failing FormRequest **throws**
  `RuntimeException` (`"Validation Failed: {json}"` / `"Authorization Failed."`)
  instead of redirecting.

## Test template

```php
<?php

declare(strict_types=1);

namespace Spartan\Tests\Unit;

use App\Models\Post;
use Spartan\Request;
use Spartan\Tests\TestCase;

final class PostTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->app->db->exec('DELETE FROM posts');          // start each test from a known state
    }

    public function test_published_returns_only_published_posts(): void
    {
        (new Post())->create(['user_id' => 1, 'title' => 'Live', 'status' => 'published']);
        (new Post())->create(['user_id' => 1, 'title' => 'Draft', 'status' => 'draft']);

        $this->assertSame(['Live'], array_column((new Post())->published(), 'title'));
    }

    public function test_the_posts_page_lists_titles(): void
    {
        (new Post())->create(['user_id' => 1, 'title' => 'Hello', 'status' => 'published']);

        $html = $this->dispatch('GET', '/posts');

        $this->assertStringContainsString('Hello', $html);
    }

    /** Run a request through the app's real routes and return the action's result. */
    private function dispatch(string $method, string $uri, array $post = []): mixed
    {
        $this->request($method, $uri, $post);                // fills $_SERVER / $_POST
        $app = $this->app;                                    // route files use $app
        require __DIR__ . '/../../routes/web.php';
        $app->router->setRequest(new Request());
        $app->router->setResponse($app->response);
        return $app->router->resolve();
    }
}
```

## TestCase helpers and state

| Use | Code |
|---|---|
| the Application | `$this->app` (`->db`, `->session`, `->router`, `->response`, `->container`) |
| fake a request | `$this->request('POST', '/posts', ['title' => 'x'], ['HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest'])` |
| fresh router + request + response | `[$router, $request, $response] = $this->router('GET', '/x');` |
| query builder | `$this->table('posts')` |
| act as a user | `$this->app->session->set('user_id', $id);` (removed again after each test) |
| temp folder for files | `$this->work` |

Assertions on responses:

```php
$this->dispatch('POST', '/posts', ['title' => 'x']);
$this->assertSame('/posts', $this->app->response->getRedirectUrl());      // redirect target
$this->assertSame(422, $this->app->response->getStatusCode());            // status
$this->assertSame(['ok' => true], json_decode((string) $this->app->response->getContent(), true));  // JSON body
```

Validation failures:

```php
$this->expectException(\RuntimeException::class);
$this->expectExceptionMessageMatches('/Validation Failed/');
$this->dispatch('POST', '/posts', ['title' => '']);
```

Replace a real service with a fake:

```php
$this->app->container->instance(\App\Services\Mailer::class, new FakeMailer());
// … run the code …
$this->app->container->forget(\App\Services\Mailer::class);
```

Tests share one Application: undo anything global a test changes (container
bindings, `Gate::define`, config values) at the end of that test. Gate rules,
`auth_user` and `user_id` are reset automatically by `TestCase::tearDown()`.

## Commands

```bash
vendor/bin/phpunit                          # everything
vendor/bin/phpunit --filter PostTest        # one class
vendor/bin/phpunit --filter test_published  # one method
vendor/bin/phpstan analyse --memory-limit=512M
php tests/run_tests.php                     # framework kernel suite
php examples/blogger/test.php               # example app suites
```

A change is finished when all of them pass.
