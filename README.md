# Spartan — Lightweight PHP MVC Framework

[![CI](https://github.com/blackmoon87/spartan/actions/workflows/ci.yml/badge.svg)](https://github.com/blackmoon87/spartan/actions/workflows/ci.yml)
[![PHP 8.1+](https://img.shields.io/badge/php-8.1%2B-777bb4)](https://www.php.net/)
[![PHPStan level 5](https://img.shields.io/badge/PHPStan-level%205-brightgreen)](phpstan.neon)
[![License MIT](https://img.shields.io/badge/license-MIT-blue)](LICENSE)

> Zero dependencies. Full control. A stack you can read end to end.

A hand-crafted PHP 8.1+ MVC framework for developers who want to understand every line of their stack. No magic, no bloat — clean architecture with security taken seriously, in about 6,000 lines of readable code.

Tested with **379 PHPUnit tests**, analysed at **PHPStan level 5**, and exercised on **PHP 8.1 through 8.4** in CI.

![TaskForge Dashboard](docs/screenshots/taskforge_dashboard_v3.png)

---

## What's Inside

| Layer | Capability |
|---|---|
| **Router** | GET / POST / PUT / PATCH / DELETE + HTML form method spoofing, middleware groups & FormRequest injection |
| **Request** | Auto JSON body parsing, file upload helpers, header resolver, client IP resolver |
| **Rate Limiter** | Parameterized rate limiting middleware (`rate_limit:100,60`) with Client IP tracking |
| **QueryBuilder** | Fluent, fully parameterized — no raw SQL. Write guards on `update()` / `delete()`, driver-aware dialects |
| **Model Relationships** | `hasMany`, `hasOne`, `belongsTo` + eager loading (no N+1) |
| **Async Job Queue** | DB-backed queue with retry + exponential backoff |
| **DI Container** | Auto-resolution via Reflection + singleton / factory / instance bindings |
| **Cache** | File or Redis driver — `Cache::remember()` pattern |
| **Events** | Synchronous and async listeners — side effects stay out of controllers |
| **Validator** | `required`, `email`, `unique`, `regex`, `nullable`, `confirmed`, `min`, `max`, `in` |
| **Logger** | PSR-3 daily rotated file logger (`storage/logs/app-YYYY-MM-DD.log`) with interpolation |
| **SQL Dialects** | Driver-aware SQL identifier quote compiling (MySQL backticks vs SQLite double quotes) |
| **FormRequests** | Abstract request base with auto-injection and auto-validation in controller methods |
| **Session** | HttpOnly + SameSite=Lax + Secure (auto-detect) + CSRF generation |
| **View** | Layout + template rendering with double path-traversal guard |
| **Security** | CSRF (form/AJAX/JSON), XSS escape, open redirect guard, security headers middleware |

---

## ⚡ Performance

Measured on this machine, with full reproduction scripts and transparent methods — see
[BENCHMARKS.md](BENCHMARKS.md) for full benchmarks and competitive comparison.

| Measurement | Result |
|---|---|
| **Router Dispatch (Bucketed/Radix)** | **~4,400,000 req/sec** |
| **DI Container Resolution (Singleton)** | **~7,600,000 ops/sec** |
| **Model Hydration (Active Record)** | **~1,100,000 models/sec** |
| **Event Dispatcher (Sync Events)** | **~2,150,000 events/sec** |
| **Gate / Authorization** | **~1,750,000 checks/sec** |
| **QueryBuilder SQL Compilation** | **~218,000 queries/sec** |
| **Live DB Roundtrips (SQLite)** | **~424,000 queries/sec** |
| **Base Memory Footprint** | **~1.5 MB (Zero memory leaks)** |
| **External Runtime Dependencies** | **0 (Zero)** |

```bash
# Run the benchmark suites:
php tests/stress_test.php                     # 2M core framework stress test
php tests/benchmark_1m_db.php                 # 1M complex DB & model hydration test
php examples/blogger/heavy_stress_test.php    # Enterprise full-app 23-stage stress test
```

What actually makes it quick is unglamorous: nothing to autoload, route patterns
compiled once, reflection metadata cached for the container and the
authorization attributes, and a template compiler that emits plain PHP.

---

## Requirements

- PHP **8.1+**
- MySQL / MariaDB (for DB features)
- Apache with `mod_rewrite` **or** PHP built-in server

---

## Getting Started

### 1. Install

Add the framework to an existing project:

```bash
composer require spartan/framework
```

Or start from this repository, which doubles as an application skeleton:

```bash
git clone https://github.com/blackmoon87/spartan.git
cd spartan
composer install
```

The framework itself lives in `framework/src` under the `Spartan\\` namespace;
your application code lives in `src/` under `App\\`.

### 2. Configure

```bash
cp .env.example .env
```

Edit `.env`:

```env
APP_NAME=Spartan
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost:8000

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=your_db
DB_USERNAME=root
DB_PASSWORD=
```

### 3. Run

```bash
php -S localhost:8000 -t public
```

Composer is optional for running the skeleton — `public/index.php` falls back to
a built-in PSR-4 autoloader that maps both `Spartan\\` and `App\\`. It is
required for the test suite and static analysis.

### 4. Database Migrations & Seeds (optional)

Run database migrations and seed default roles and permissions:

```bash
# Run migrations
php spartan migrate

# Seed database
php spartan db:seed
```

### 5. Async Queue (optional)

Start the worker via the CLI runner:

```bash
# Single pass (use with Cron — every minute)
php spartan worker

# Continuous loop (development / daemon)
php spartan worker --loop
```

---

## Structure

```
├── BENCHMARKS.md               # Full competitive performance benchmark report
├── config/
│   └── config.php              # .env loader → config array
├── database/
│   └── migrations/             # Dialect-aware SQL migrations
├── examples/
│   ├── shop/                   # Example 1: E-commerce Store
│   ├── blogger/                # Example 2: Enterprise Publishing Platform
│   ├── taskforge/              # Example 3: 100% Feature Verification App (36/36 tests)
│   └── css_showcase/           # Example 4: Multi-CSS Framework Integration (Tailwind, Open Props, Vanilla)
├── public/
│   ├── .htaccess               # Apache URL rewriting
│   └── index.php               # Front controller entry point
├── routes/
│   ├── web.php                 # Public web routes
│   ├── admin.php               # Protected routes
│   └── api.php                 # JSON API routes
├── framework/
│   └── src/                    # The spartan/framework package — namespace Spartan\
│       ├── Application.php     # App orchestrator & singleton
│       ├── Attributes/         # PHP 8.1 attributes (#[RequireRole], #[RequirePermission])
│       ├── Auth.php            # Session-backed auth
│       ├── Cache.php           # Cache facade (file & Redis)
│       ├── CacheDrivers/       # FileCacheDriver, RedisCacheDriver
│       ├── Container.php       # DI container with reflection caching
│       ├── Controller.php      # Base controller
│       ├── Database.php        # PDO connection
│       ├── Database/           # Migrator, SqliteDialect, MysqlDialect
│       ├── EventDispatcher.php # Sync & async events
│       ├── ExceptionHandler.php# Error rendering
│       ├── FormRequest.php     # Request base with auto-validation
│       ├── Gate.php            # Abilities, policies & GateEvaluator
│       ├── JobQueue.php        # Async job runner with backoff
│       ├── Logger.php          # PSR-3 daily rotated logger
│       ├── Middleware.php      # Middleware base
│       ├── Middlewares/        # CSRF, auth, rate limit, security headers
│       ├── Model.php           # Active record & relationships
│       ├── Paths.php           # Project root resolver
│       ├── QueryBuilder.php    # Dialect-aware fluent query builder
│       ├── RelationQuery.php   # hasMany / hasOne / belongsTo executor
│       ├── Request.php         # HTTP request, method spoofing, trusted proxies
│       ├── Response.php        # HTTP response & open-redirect guard
│       ├── Router.php          # Router & attribute inspector
│       ├── Session.php         # Hardened session manager
│       ├── Traits/             # HasAuthorization
│       ├── Validator.php       # Validation rules
│       ├── View.php            # Blade-style compiler & layouts
│       └── helpers.php         # url(), asset(), auth(), env(), config()
├── src/                        # Your application — namespace App\
│   ├── Controllers/
│   ├── Models/
│   ├── Services/
│   ├── Listeners/
│   ├── Events/
│   ├── Middlewares/
│   └── Views/
│       └── layouts/
├── storage/
│   ├── cache/                  # Fast file cache store
│   ├── logs/                   # PSR-3 daily log files
│   └── views/                  # Compiled Blade PHP templates
├── tests/
│   ├── Unit/                   # PHPUnit suite (379 tests)
│   ├── Fixtures/               # Test doubles & fixture models
│   ├── bootstrap.php           # Boots the app against a throwaway SQLite DB
│   ├── run_tests.php           # Dependency-free kernel suite (33 checks)
│   └── stress_test.php         # Micro-benchmark suite
├── .env
├── .env.example
├── .cursorrules                # AI IDE architecture rules
└── composer.json
```

---

## 📸 Screenshots Showcase

### 1. TaskForge — Complete Feature Verification App (`examples/taskforge`)
Dark-mode SaaS dashboard with task tracking, project metrics, role-based access control (RBAC), and 36 automated unit tests.

![TaskForge Dashboard](docs/screenshots/taskforge_dashboard_v3.png)

### 2. Spartan Blogger — Enterprise Publishing Platform (`examples/blogger`)
Glassmorphic publication platform featuring article analytics, comment management, clap engine, and HTMX partial swaps.

| Home Page | Article Detail Page |
|---|---|
| ![Blogger Home](docs/screenshots/blogger_home_v3.png) | ![Blogger Post](docs/screenshots/blogger_post_v3.png) |

### 3. Spartan Shop — E-Commerce Engine (`examples/shop`)
Production e-commerce storefront with shopping cart transactions, product listings, and order checkout pipelines.

![Shop Home](docs/screenshots/shop_home_v3.png)

### 4. CSS Multi-Support & Interactive Motion Showcase (`examples/css_showcase`)
High-end UI component suite rendering Tailwind CSS, Open Props, auto-advancing carousel sliders, CSS shimmer keyframe loaders, and responsive aspect-ratio media galleries.

| Interactive Sliders & Keyframe Motion | Tailwind CSS Component Suite |
|---|---|
| ![Interactive Motion](docs/screenshots/css_motion_interactive_v3.png) | ![Tailwind Suite](docs/screenshots/css_tailwind_suite_v3.png) |

---

## Core Examples

### Routing

Spartan features a robust, regex-based router that supports RESTful methods, route parameters, middleware piping, and form method spoofing.

#### 1. Defining Route Types
Define routes in their respective files under the `routes/` directory depending on their context:
* **Web Routes (`routes/web.php`)**: For standard browser pages (GET) and web forms (POST).
* **Protected/Admin Routes (`routes/admin.php`)**: For routes requiring authentication or specific security checks. Attach middlewares to these routes:
  ```php
  $app->router->get('/admin/dashboard', [AdminController::class, 'index'], [
      SecurityHeadersMiddleware::class,
      AuthMiddleware::class,
  ]);
  ```
* **API Routes (`routes/api.php`)**: For stateless JSON API endpoints.

#### 2. Parameterized Routes
Capture dynamic URL segments using curly braces `{param}`. These are automatically extracted and passed to your controller action as arguments:
```php
// Route Definition
$app->router->get('/users/{userId}/orders/{orderId}', [UserController::class, 'showOrder']);

// Controller Action
class UserController extends Controller {
    public function showOrder(string $userId, string $orderId) {
        // Automatically populated from the URL segments
    }
}
```

#### 3. Form Method Spoofing
Native HTML forms only support `GET` and `POST`. To perform RESTful `PUT`, `PATCH`, or `DELETE` requests from a form, add a hidden `_method` field. The router intercepts this field and directs the request to the correct handler:
```html
<form method="POST" action="/orders/42">
    <!-- CSRF Protection -->
    @csrf 
    <!-- Method Spoofing -->
    <input type="hidden" name="_method" value="DELETE">
    <button type="submit">Cancel Order</button>
</form>
```

---

### View & Backend Integration

The view layer (`V`) acts as the presentation layer, integrating with the controller (`C`) by receiving structured variables, rendering layouts, and handling client-side state reactively via HTMX and Alpine.js.

#### 1. Passing Variables from Backend to Frontend
In your controller, you return a rendered template by passing an associative array containing the variables:
```php
class DashboardController extends Controller {
    public function index() {
        $stats = $this->model->getStatistics();
        return $this->render('dashboard', [
            'stats' => $stats,
            'title' => 'Admin Panel'
        ]);
    }
}
```
Behind the scenes:
- **Native PHP Views (`.php`)**: The framework extracts the associative array into local variables using PHP's `extract()` function inside the `View` object's execution context. You print them using `$this->escape($title)` or `<?= $this->escape($title) ?>`.
- **Blade Views (`.blade.php`)**: The framework compiles Blade directives natively using a regex-based compiler (extracted from the spirit of BladeOne). You print variables using `{{ $title }}` (which is escaped by default) or `{!! $title !!}` (for raw, unescaped HTML).

#### 2. Hybrid Render Methods
* **`render($view, $data)`**: Compiles the template and wraps it inside a main layout (e.g. `layouts/main_blade.blade.php`), yielding the template content inside the `@yield('content')` block.
* **`renderViewOnly($view, $data)`**: Compiles the template and returns only its raw HTML content without wrapping it in a layout. This is perfect for returning partial AJAX fragments to **HTMX** requests.

#### 3. Frontend Interactivity (HTMX & Alpine.js Flow)
HTMX handles server-client updates without writing complex JavaScript, while Alpine.js handles local client state.
* **HTMX AJAX Swap**: Send a request asynchronously on keystrokes or button clicks, and swap the returned partial template directly into a DOM node:
  ```html
  <!-- Views/search.blade.php -->
  <input type="text" name="query" 
         hx-post="/search/query" 
         hx-trigger="keyup changed delay:300ms" 
         hx-target="#search-results" 
         hx-include="[name=_csrf]"
         placeholder="Type to search...">

  <div id="search-results">
      <!-- The search_results.blade.php view will be injected here -->
  </div>
  ```
* **Controller handler (Backend)**: Receives the POST query, fetches filtered data, and returns only the partial view snippet:
  ```php
  public function searchQuery() {
      $query = $this->request->post('query');
      $results = (new Customer)->search($query);
      
      // Render ONLY the partial list view
      return $this->renderViewOnly('search_results', ['users' => $results]);
  }
### Middlewares & Rate Limiting

Spartan supports mapping middleware aliases and groups in `Router.php` and passing dynamic parameters (e.g. `rate_limit:limit,window`).

#### 1. Defining Parameterized Middleware Routes
```php
$app->router->get('/dashboard', [DashboardController::class, 'index'], [
    'auth',
    'rate_limit:100,60' // Max 100 requests per 60 seconds (IP-based)
]);
```

#### 2. How the Rate Limiter Middleware Works
The `RateLimitMiddleware` checks the user's IP address and rate limits the route dynamically:
* In case of violations, it returns a `429 Too Many Requests` status code and terminates the route cycle early.
* Adds standard headers: `X-RateLimit-Limit`, `X-RateLimit-Remaining`, and `Retry-After`.

---

### Request & API Inputs

Spartan's Request class encapsulates all query parameters, form data, uploaded files, and HTTP headers.

#### 1. Automatic JSON Body Parsing
When a client sends a request with `Content-Type: application/json`, the request body is automatically decoded and merged. You can retrieve inputs using standard methods:
```php
// Automatically parses JSON input: {"title": "Hello"}
$title = $this->request->input('title');
$body  = $this->request->getBody();
```

#### 2. Request Headers Helper
```php
$token = $this->request->header('Authorization'); // Bearer <token>
```

#### 3. File Uploads Helper
Never access `$_FILES` directly. Use:
```php
$file = $this->request->file('avatar'); // Retrieves file array
$allFiles = $this->request->getFiles();

// Upload path configured in config/config.php
$uploadDir = Application::$app->config['storage']['uploads'];
```

---

### QueryBuilder

```php
// Fluent, fully parameterized
$this->table()->where('active', 1)->orderBy('name')->paginate(15, $page);

// Write guards — throws LogicException without where()
$this->table()->where('id', $id)->update(['status' => 'active']);
$this->table()->where('id', $id)->delete();
```

### Models & Hydration

Models return hydrated object instances instead of raw arrays when using finding helpers:

```php
// Find record by primary key ID and return hydrated Model instance
$user = (new User)->findInstance(1);
echo $user->name;

// Find record by any unique column (e.g. slug) and return hydrated Model instance
$post = (new Post)->findInstanceBy('slug', 'my-first-post');
echo $post->title;
```

### Model Relationships

```php
class User extends Model
{
    protected string $table = 'users';

    public function orders(): RelationQuery
    {
        return $this->hasMany(Order::class, foreignKey: 'user_id');
    }
}

// Single record
$user   = (new User)->find(1);
$orders = (new User)->orders()->for($user);

// Eager load — 2 queries total, no N+1
$users = (new User)->all();
$users = (new User)->orders()->loadFor($users, as: 'orders');
```

### Async Events

```php
// Register — async/sync per listener
$app->events->listen('order.placed', UpdateInventory::class);              // sync
$app->events->listen('order.placed', SendOrderSms::class,
    async: true, maxAttempts: 3, onFailure: 'retry'                        // async
);

// Dispatch — identical regardless
$this->event('order.placed', $order);
```

### Validation

```php
$v = $this->validate($this->request->getBody(), [
    'email'    => 'required|email|unique:users,email',
    'password' => 'required|min:8|max:64',
    'phone'    => 'nullable|string|regex:/^\+?[0-9]{7,15}$/',
]);

if ($v->fails()) {
    return $this->render('register', ['errors' => $v->errors()]);
}
```

### Daily Logger (PSR-3)

```php
use Spartan\Application;

// Log informative message with placeholder injection
Application::$app->logger->info("User {username} performed an action", [
    'username' => 'john_doe'
]);

// Logs go to: storage/logs/app-YYYY-MM-DD.log
// Uncaught exceptions are automatically logged with traces by ExceptionHandler.
```

### SQL Dialects

The `QueryBuilder` automatically compiles queries with quotes appropriate to the active driver:
- **MySQL**: Compiles table/column names using backticks:
  ```sql
  SELECT `id`, `name` FROM `users` WHERE `active` = 1
  ```
- **SQLite**: Compiles table/column names using double quotes:
  ```sql
  SELECT "id", "name" FROM "users" WHERE "active" = 1
  ```

### FormRequests

Encapsulate your validation and authorization logic into dedicated Request objects. `$this->session` and `$this->auth` instances are automatically bound in `FormRequest`:

```php
namespace App\Controllers\Requests;

use Spartan\FormRequest;

class StorePostRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->session->get('role') === 'admin' || $this->session->get('role') === 'author';
    }

    public function rules(): array
    {
        return [
            'title' => 'required|string|min:5|max:100',
            'body'  => 'required|string',
        ];
    }
}
```

Type-hint the FormRequest in your controller action, and the Router will automatically validate and inject it:

```php
public function store(StorePostRequest $request)
{
    // Execution only reaches here if validation and authorization pass.
    $validatedData = $request->getBody();
    
    (new Post)->create($validatedData);
    return $this->redirect('/posts');
}
```

---

### Blade Directives & View Engine

Spartan features a lightweight, native Blade compiler (`View.php`) supporting dot-notation view paths (e.g. `shop.partials.product_grid` or `blog/show`) and built-in Blade directives:

| Directive | Compiled Output / Description |
|---|---|
| `{{ $var }}` | Escaped HTML output (`htmlspecialchars`) |
| `{!! $var !!}` | Raw unescaped HTML output |
| `@csrf` | Hidden CSRF token field (`<input type="hidden" name="_csrf" ...>`) |
| `@extends('layout')` | Layout inheritance wrapper |
| `@section('name') ... @endsection` | Named template content section |
| `@yield('name')` | Yield section content inside layout |
| `@include('view.name')` | Partial template inclusion (supports dot or slash notation) |
| `@flash('key') ... {{ $flashMsg }} ... @endflash` | Conditional flash message block |
| `@can('permission') ... @endcan` | Gate authorization block check |
| `@cannot('permission') ... @endcannot` | Gate authorization denial check |
| `@role('admin') ... @endrole` | RBAC user role authorization block check |

---

### RBAC & Authorization Attributes

Protect entire Controller classes or specific action methods using native PHP 8.1+ attributes. The `Router` inspects attributes during resolution via Reflection:

```php
namespace App\Controllers;

use Spartan\Attributes\RequireRole;
use Spartan\Attributes\RequirePermission;
use Spartan\Controller;

#[RequireRole('author')]
#[RequirePermission('publish_posts')]
class AuthorPostController extends Controller
{
    public function index()
    {
        // Protected action — requires user to have 'author' role & 'publish_posts' permission
    }
}
```

---

### Third-Party Composer Library Integration

Spartan maintains a zero-dependency core kernel (`framework/src/`), but supports 100% seamless integration with any third-party Composer packages:

```bash
# Example: Install Carbon DateTime library
composer require nesbot/carbon
```

```php
// Use in views or controllers directly:
echo \Carbon\Carbon::now()->subMinutes(15)->diffForHumans();
// Output: "15 minutes ago"
```

---

## Example Applications

Spartan includes two full-fledged, production-ready example applications in the `examples/` directory:

### 1. E-Commerce Storefront ([examples/shop](file:///Users/blackmoon/Desktop/working/spartan/examples/shop))
- Features: Product catalog, cart management, atomic DB transactions checkout, stock deduction, HTMX product search, REST JSON API endpoints, and glassmorphic UI.
- Run tests: `php examples/shop/test.php` (15/15 passed).
- Run server: `cd examples/shop && php -S localhost:8085 -t public`

### 2. Enterprise Blogger Platform ([examples/blogger](file:///Users/blackmoon/Desktop/working/spartan/examples/blogger))
- Features: Article publishing, category filtering, HTMX live search, article claps/likes, newsletter subscriptions, real-time analytics API, audit logs, and author publishing portal protected by `#[RequireRole('author')]`.
- Run tests: `php examples/blogger/test.php` (24/24 passed).
- Run server: `cd examples/blogger && php -S localhost:8086 -t public`

---

## Test Suites

Spartan includes comprehensive, automated test suites verifying 100% of the kernel engine and application features:

```bash
# 1. Independent Framework Kernel Test Suite (22/22 Passed)
php tests/run_tests.php

# 2. E-Commerce Shop Example Test Suite (15/15 Passed)
php examples/shop/test.php

# 3. Enterprise Blogger Platform Test Suite (24/24 Passed)
php examples/blogger/test.php
```

---

## Security

| Threat | Mitigation |
|---|---|
| SQL Injection | QueryBuilder — values bound as parameters; operators and column names checked against a whitelist |
| XSS | `$this->escape()` in all views — `ENT_QUOTES UTF-8` |
| CSRF | Token validated on every state-changing verb — POST, PUT, PATCH, DELETE (form / AJAX header / JSON body) |
| Session Fixation | `Session::regenerate()` after every login |
| Path Traversal | Regex + `realpath()` double-guard in View |
| Open Redirect | `Response::redirect()` blocks external domains |
| Clickjacking | `SecurityHeadersMiddleware` — X-Frame-Options: SAMEORIGIN |
| MIME Sniffing | X-Content-Type-Options: nosniff |
| IP Spoofing | `X-Forwarded-For` honoured only for peers listed in `TRUSTED_PROXIES` |
| Rate Limit Evasion | Counters incremented atomically (file lock / Redis `INCR`) |
| Cross-Request Identity Leaks | Worker mode clears the cached user + session between requests |

> **Behind a load balancer?** Set `TRUSTED_PROXIES` in `.env` (IPs, CIDRs, or `*`).
> Left empty — the default — forwarded headers are ignored and `REMOTE_ADDR` wins,
> so nobody can forge an IP to dodge the rate limiter.

---

## AI IDE Rules

The `.cursorrules` file encodes the full architecture as enforceable rules for AI assistants (Cursor, GitHub Copilot, etc.). It covers:

- Directory structure and namespace conventions
- Security rules (mandatory escape, CSRF in every form, session regeneration)
- QueryBuilder API reference
- Relationship patterns and eager loading rules
- Async queue configuration
- Feature workflow (route → service → model → controller → view)

---

## Philosophy

- **No magic** — every class is explicit and traceable
- **No raw SQL** — QueryBuilder only, write guards enforced
- **No inline side effects** — SMS/email/PDF goes through Listeners
- **No hardcoded config** — everything via `.env`
- **Explicit over implicit** — `foreignKey` is always declared, never guessed

---

## License

MIT

