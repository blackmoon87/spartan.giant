# JSON APIs — endpoints, token auth, CORS, errors

Source: `framework/src/Router.php`, `Controller.php`, `Request.php`,
`FormRequest.php`, `ExceptionHandler.php`, `Gate.php`.

## Routes and controllers

Put API routes in `routes/api.php` with a version prefix (there is no route
group prefix — write the full path):

```php
use App\Controllers\Api\PostApiController;

$app->router->get('/api/v1/posts', [PostApiController::class, 'index']);
$app->router->get('/api/v1/posts/{id}', [PostApiController::class, 'show']);
$app->router->post('/api/v1/posts', [PostApiController::class, 'store'], ['api.auth']);
$app->router->excludeCsrf('/api/*');        // token-authenticated APIs carry no CSRF token
```

```php
<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Controllers\Requests\StorePostRequest;
use App\Models\Post;
use Spartan\Controller;
use Spartan\Gate;

class PostApiController extends Controller
{
    public function index(): void
    {
        $page = max(1, (int) $this->request->get('page', 1));
        $this->json((new Post())->table()->orderBy('id', 'DESC')->paginate(20, $page));
    }

    public function show(int $id): void
    {
        $post = (new Post())->find($id);
        $post === null
            ? $this->json(['error' => 'Not found.'], 404)
            : $this->json(['data' => $post]);
    }

    public function store(StorePostRequest $request): void
    {
        $user = Gate::resolveUser();                       // set by the token middleware
        $data = $request->getBody();
        $id   = (new Post())->create(['user_id' => $user->id, 'title' => $data['title'], 'body' => $data['body']]);
        $this->json(['data' => (new Post())->find($id)], 201);
    }
}
```

## What API clients must send

| Header | Why |
|---|---|
| `Content-Type: application/json` | JSON bodies are parsed into `post()`/`getBody()`; a failed FormRequest answers **422 JSON** instead of redirecting |
| `X-Requested-With: XMLHttpRequest` | uncaught errors answer **JSON 500** instead of an HTML page (or use the API exception handler below) |
| `Authorization: Bearer <token>` | token auth (below) |

Validation failure (422):

```json
{"message": "The given data was invalid.", "errors": {"title": "The title field is required."}}
```

## Token authentication

Migration `database/migrations/0003_create_api_tokens.sql`:

```sql
CREATE TABLE IF NOT EXISTS api_tokens (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    user_id      INT NOT NULL,
    token_hash   CHAR(64) NOT NULL UNIQUE,
    created_at   DATETIME NULL,
    last_used_at DATETIME NULL
);
```

Issue a token (show the plain token to the user once; store only its hash):

```php
$plain = bin2hex(random_bytes(32));
(new \Spartan\QueryBuilder(\Spartan\Application::$app->db, 'api_tokens'))->insert([
    'user_id'    => $userId,
    'token_hash' => hash('sha256', $plain),
    'created_at' => gmdate('Y-m-d H:i:s'),
]);
// return $plain to the client
```

Middleware `src/Middlewares/ApiTokenAuth.php`:

```php
<?php

declare(strict_types=1);

namespace App\Middlewares;

use App\Models\User;
use Spartan\Application;
use Spartan\Middleware;
use Spartan\QueryBuilder;
use Spartan\Request;
use Spartan\Response;

class ApiTokenAuth extends Middleware
{
    public function execute(Request $request, Response $response): void
    {
        $header = (string) $request->header('Authorization');
        $plain  = str_starts_with($header, 'Bearer ') ? substr($header, 7) : '';

        $row = $plain === '' ? null
            : (new QueryBuilder(Application::$app->db, 'api_tokens'))->where('token_hash', hash('sha256', $plain))->first();
        $user = $row === null ? null : (new User())->findInstance($row['user_id']);

        if ($user === null) {
            $response->json(['error' => 'Unauthenticated.'], 401);     // stops the route
            return;
        }

        // The request's user for Gate::resolveUser(), @can, #[RequireRole] and policies.
        Application::$app->container->instance('auth_user', $user);
    }
}
```

Register it in `public/index.php`:
`$app->router->aliasMiddleware('api.auth', \App\Middlewares\ApiTokenAuth::class);`

In token-authenticated code read the user with `\Spartan\Gate::resolveUser()`
(`auth()->user()` reads the **session** login). `#[RequireRole]`,
`#[RequirePermission]`, `Gate::allows()` and policies all see the token user.
Revoke a token by deleting its row.

## CORS (browser apps on another domain)

Register as a **global** middleware so it also answers preflight `OPTIONS`
requests, which have no route:

```php
<?php

declare(strict_types=1);

namespace App\Middlewares;

use Spartan\Middleware;
use Spartan\Request;
use Spartan\Response;

class Cors extends Middleware
{
    private const ALLOWED_ORIGINS = ['https://app.example.com'];

    public function handle(Request $request, Response $response, callable $next): mixed
    {
        $origin = (string) $request->header('Origin');
        if (in_array($origin, self::ALLOWED_ORIGINS, true)) {
            $response->setHeader('Access-Control-Allow-Origin', $origin);
            $response->setHeader('Vary', 'Origin');
            $response->setHeader('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With');
            $response->setHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, PATCH, DELETE');
        }

        if ($request->getMethod() === 'OPTIONS') {               // preflight: answer now
            $response->setStatusCode(204);
            $response->setContent('');
            return $response;
        }

        return $next();
    }
}
```

```php
// public/index.php
$app->router->setGlobalMiddlewares([
    \App\Middlewares\Cors::class,
    \Spartan\Middlewares\CsrfMiddleware::class,
]);
```

## JSON errors for every /api request

Without `X-Requested-With`, an uncaught exception renders the HTML error page.
To answer JSON on every `/api/…` path regardless of headers:

```php
<?php

declare(strict_types=1);

namespace App;

use Spartan\ExceptionHandler;
use Spartan\Request;
use Spartan\Response;

class ApiAwareExceptionHandler extends ExceptionHandler
{
    public function handle(\Throwable $e, Request $request, Response $response, array $config): void
    {
        if (!str_starts_with($request->getPath(), '/api/')) {
            parent::handle($e, $request, $response, $config);
            return;
        }
        \Spartan\Application::$app->logger->error($e->getMessage(), ['file' => $e->getFile(), 'line' => $e->getLine()]);
        $response->reset();
        $response->json(['error' => ($config['app']['debug'] ?? false) ? $e->getMessage() : 'Server error.'], 500);
        $response->send();
    }
}
```

```php
// public/index.php
$app->container->singleton(\Spartan\ExceptionHandler::class, fn() => new \App\ApiAwareExceptionHandler());
```

## Rate limiting

```php
// public/index.php
$app->router->aliasMiddleware('throttle', \Spartan\Middlewares\RateLimitMiddleware::class);
// routes/api.php
$app->router->post('/api/v1/login', [AuthApiController::class, 'login'], ['throttle:10,60']);
```

Over the limit the client gets `429` with `Retry-After`; every response carries
`X-RateLimit-Limit` / `X-RateLimit-Remaining` / `X-RateLimit-Reset`.
