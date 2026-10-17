# HTTP — routes, controllers, requests, responses, middleware

Source: `framework/src/Router.php`, `Controller.php`, `Request.php`, `Response.php`,
`Middleware.php`, `Middlewares/*`.

## Routes

Route files (`routes/web.php`, `api.php`, `admin.php`) have `$app` in scope:

```php
use App\Controllers\PostController;
use Spartan\Middlewares\AuthMiddleware;

$app->router->get('/posts', [PostController::class, 'index']);
$app->router->get('/posts/{id}', [PostController::class, 'show']);          // {id} → $id
$app->router->post('/posts', [PostController::class, 'store'], [AuthMiddleware::class]);
$app->router->put('/posts/{id}', [PostController::class, 'update'], [AuthMiddleware::class]);
$app->router->patch('/posts/{id}', [PostController::class, 'patch']);
$app->router->delete('/posts/{id}', [PostController::class, 'destroy'], [AuthMiddleware::class]);
$app->router->get('/ping', fn() => 'pong');                                 // closure route
$app->router->redirect('/old', '/new', 301);
$app->router->fallback(fn() => 'Custom not found');                         // instead of 404 page
```

- Placeholders `{name}` match one path segment; the value is passed to the
  action parameter **with the same name**.
- Type a parameter `int` only when it is always numeric (`/posts/abc` → error);
  use `string` otherwise.
- HTML forms send `PUT`/`PATCH`/`DELETE` with `@method('PUT')` inside a POST form.
- Unmatched routes render `src/Views/error_404.php` with status 404.
- Route caching (`ROUTE_CACHE_ENABLED=true`) needs controller routes: closures
  cannot be cached.

## Controller template

```php
<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Controllers\Requests\StorePostRequest;
use App\Models\Post;
use Spartan\Controller;

class PostController extends Controller
{
    public function index(): string
    {
        return $this->render('posts/index', ['posts' => (new Post())->published()]);
    }

    public function show(int $id): string
    {
        $post = (new Post())->find($id);
        if ($post === null) {
            $this->response->setStatusCode(404);
            return $this->render('error_404', ['message' => trans('app.messages.not_found')]);
        }
        return $this->render('posts/show', ['post' => $post]);
    }

    // A FormRequest parameter is validated before the action runs.
    public function store(StorePostRequest $request): void
    {
        $data = $request->getBody();
        (new Post())->create([
            'user_id' => auth()->id(),
            'title'   => $data['title'],
            'body'    => $data['body'],
        ]);
        $this->session->setFlash('success', trans('posts.published'));
        $this->redirect('/posts');
    }
}
```

What an action returns:

| Goal | Write | Return type |
|---|---|---|
| HTML page | `return $this->render('view', $data);` | `string` |
| page without layout (HTMX/partial) | `return $this->renderViewOnly('view', $data);` | `string` |
| JSON | `$this->json($data, 200);` | `void` |
| redirect | `$this->redirect('/path');` then `return;` | `void` |
| status code / header | `$this->response->setStatusCode(201);` `$this->response->setHeader('X-A', 'b');` | — |

Inside a controller: `$this->request`, `$this->response`, `$this->session`, `$this->auth`, plus
`$this->validate($data, $rules)`, `$this->make(Service::class)`,
`$this->event('name', $payload)`, `$this->setLayout('x')`, `$this->share('key', $v)`.

**Mandatory translation rule for controllers**: Never pass raw text in
`$this->session->setFlash(...)` or JSON messages returned to the client.
Always use `trans('group.key', [...])` or `__('group.key', [...])` so responses
support multiple locales (`lang/{locale}/{group}.php`).

Constructor injection (resolved by the container):

```php
public function __construct(private PostService $posts)
{
    parent::__construct();      // required: sets request/response/session
}
```

## Request — `$this->request`

| Method | Returns |
|---|---|
| `get('page', 1)` | query string value (`$_GET`) |
| `post('email')` | form or JSON body value |
| `input('q')` | body, then JSON, then query string |
| `getBody()` | all of GET + POST + JSON merged (JSON wins) |
| `header('X-Api-Key')` | header value or null |
| `getMethod()` | `GET`/`POST`/`PUT`/`PATCH`/`DELETE` (honours `_method`) |
| `isPost()` | real POST (including spoofed PUT/PATCH/DELETE) |
| `isAjax()` | `X-Requested-With: XMLHttpRequest` or HTMX `HX-Request` |
| `getIp()` | client IP (proxies honoured only via `TRUSTED_PROXIES`) |
| `getPath()` | path without query string or base folder |
| `upload('avatar')` | `?UploadedFile` (see services guide) |

## Middleware

Attach per route (`[AuthMiddleware::class]`), as a named group, or globally:

```php
// public/index.php — the global list REPLACES the default; keep CsrfMiddleware in it.
$app->router->setGlobalMiddlewares([
    \Spartan\Middlewares\CsrfMiddleware::class,
    \Spartan\Middlewares\SecurityHeadersMiddleware::class,
]);
$app->router->aliasMiddleware('throttle', \Spartan\Middlewares\RateLimitMiddleware::class);
$app->router->middlewareGroup('admin', [\Spartan\Middlewares\AuthMiddleware::class, 'throttle:30,60']);

$app->router->post('/login', [AuthController::class, 'login'], ['throttle:5,60']);  // 5 per 60 s
$app->router->get('/admin', [AdminController::class, 'index'], ['admin']);
$app->router->excludeCsrf('/webhooks/*');      // stateless endpoints only
```

Built-in middleware:

| Class | Does |
|---|---|
| `AuthMiddleware` | redirects to `/login` when no `user_id` is in the session |
| `CsrfMiddleware` | rejects POST/PUT/PATCH/DELETE without a valid token (403) |
| `RateLimitMiddleware(limit, window)` | 429 after `limit` hits per IP+path per `window` seconds |
| `SecurityHeadersMiddleware(csp)` | X-Frame-Options, nosniff, Referrer-Policy, CSP (`default-src 'self'`) |

Writing middleware — a **guard** (check, then reject or continue):

```php
class RequireApiKey extends \Spartan\Middleware
{
    public function execute(\Spartan\Request $request, \Spartan\Response $response): void
    {
        if ($request->header('X-Api-Key') !== env('API_KEY')) {   // API_KEY in .env
            $response->json(['error' => 'Invalid API key.'], 401);   // stops the route
        }
    }
}
```

A **wrapper** (code before and after the route):

```php
class Timing extends \Spartan\Middleware
{
    public function handle(\Spartan\Request $request, \Spartan\Response $response, callable $next): mixed
    {
        $start  = hrtime(true);
        $result = $next();                                   // runs the route
        $response->setHeader('X-Time-Ms', (string) round((hrtime(true) - $start) / 1e6, 2));
        return $result;
    }
}
```

The route is skipped when a middleware sets a redirect, a body, or a status
≥ 400 — or returns without calling `$next()`.

Register `setGlobalMiddlewares()` and `aliasMiddleware()` in `public/index.php`.
With `ROUTE_CACHE_ENABLED=true` the route files are not loaded; routes, groups
and CSRF exclusions come from the cache, but aliases and global middleware do not.

## CSRF

Global by default. Forms: `@csrf`. AJAX/fetch: send header `X-CSRF-Token` with
the value of `$this->csrfTokenValue()` (put it in a `<meta>` tag in the layout).
JSON bodies may carry `_csrf` instead.

## Errors

- Uncaught exceptions → status 500 and `src/Views/error_500.php` (falls back to a
  plain message). With `APP_DEBUG=true` the message and trace are shown.
- Stop a request with a prepared response from anywhere:
  `throw new \Spartan\HttpResponseException($response);` — it is sent as-is.
- Custom handler: `$app->container->singleton(\Spartan\ExceptionHandler::class, fn() => new MyHandler());`
