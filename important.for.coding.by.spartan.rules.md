# Spartan MVC (spartan.giant) — The Definitive 100% Architectural Law & Reference

> **ZERO EXTERNAL DEPENDENCIES • ZERO FRAMEWORK BLOAT • 100% EXPLICIT ARCHITECTURE**  
> Never assume Laravel, Symfony, or Yii conventions. This document is the complete and exhaustive law for developing inside Spartan MVC (`spartan.giant`).

---

## 1. Absolute Prohibitions (The 11 Taboos)

| Forbidden Practice | Why It Is Forbidden | What You Must Use Instead |
|---|---|---|
| Direct `$_GET`, `$_POST`, `$_FILES`, `$_SESSION`, `$_SERVER` | Bypasses request sanitization and breaks worker-mode isolation. | `$this->request->*`, `$this->session->*` |
| `header("Location: ...")`, `http_response_code()` | Bypasses Spartan's Response object and open-redirect protection. | `$this->redirect($url)`, `$this->response->setStatusCode($code)` |
| Raw `echo $var`, `<?= $var ?>` in views | Vulnerable to Cross-Site Scripting (XSS). | `{{ $var }}` (Blade) or `$this->escape($var)` (PHP) |
| Raw SQL strings in Controllers or Views | Breaks separation of concerns and creates SQL injection risks. | Models via `$this->table()->...` (QueryBuilder) |
| Calling DB queries or models inside Views | Hard veto. Views are 100% presentation only. | Pass data from Controller via `$this->render('view', $data)` |
| Hardcoded paths, URLs, or credentials | Breaks environment portability and security. | `.env` &rarr; `config/config.php` &rarr; `Application::$app->config` |
| Manual `session_start()` calls | Conflicts with Spartan's hardened cookie and worker lifecycle. | Session auto-initializes in `Spartan\Session` |
| `isset()` / `empty()` validation spaghetti in Controllers | Causes duplicated, unmaintainable validation logic. | `$this->validate($data, $rules)` or `FormRequest` |
| Inventing non-existent Laravel helpers (e.g. `redirect()`, `view()`) | Spartan is strictly OOP. Use defined helpers only. | `$this->redirect()`, `$this->render()` |
| Calling `$model->orders()->for($item)` inside loops | Causes $N+1$ database performance destruction. | Eager load via `->loadFor($items, as: 'orders')` |
| Direct property access `$model->table` from outside | Violates encapsulation. | Always call `$model->getTable()` |

---

## 2. Curated Global Helpers (`framework/src/helpers.php`)

Spartan includes a strictly curated set of fast, global helper functions:

```php
// 1. URL & Assets
url('/orders/123');              // Generates absolute application URL based on request base path
asset('css/app.css');            // Alias for url() to point to public assets

// 2. Authentication (Spartan\AuthInterface)
auth();                          // Returns Spartan\AuthInterface (Application::$app->auth)
auth()->check();                 // Returns bool (is user logged in?)
auth()->id();                    // Returns int|string|null (current user ID)
auth()->user();                  // Returns hydrated User Model or null

// 3. Environment & Configuration
env('DB_DATABASE', 'default_db'); // Reads $_ENV with type coercion ('true' -> true, 'null' -> null)
config('db.database');           // Reads config with dot notation (Application::$app->config)
config('app.name', 'Spartan');

// 4. Internationalization & Translation (i18n)
trans('app.welcome', ['name' => 'Ahmad']); // Translates key from lang/{locale}/app.php
__('app.welcome', ['name' => 'Ahmad']);    // Alias for trans()
current_locale();                          // Returns current active locale string (e.g. 'en', 'ar')
is_rtl();                                  // Returns bool if active or given locale is RTL

// 5. Path Resolution (Spartan\Paths)
Paths::base('src/Views');        // Absolute path relative to project root
Paths::storage('logs');          // Absolute path to storage directory
```

---

## 3. Directory Layout & Layer Responsibilities

```
MaxOrder/
├── config/
│   └── config.php          ← Loads .env variables and returns centralized config array
├── database/
│   ├── migrations/         ← Sequenced migrations (0001_*.sql & 0001_*_down.sql)
│   └── seed.sql            ← Default roles, permissions, and seed data
├── framework/src/          ← Spartan Core Framework package (Spartan\ namespace)
│   ├── Attributes/         ← PHP 8.1 Attributes (#[RequireRole], #[RequirePermission])
│   ├── CacheDrivers/       ← FileCacheDriver, RedisCacheDriver
│   ├── Database/           ← Migrator, MysqlDialect, SqliteDialect
│   ├── Middlewares/        ← AuthMiddleware, CsrfMiddleware, RateLimitMiddleware, SecurityHeadersMiddleware
│   ├── QueueDrivers/       ← DatabaseQueueDriver, RedisQueueDriver
│   ├── Traits/             ← HasAuthorization
│   └── Translation/        ← Translator (i18n engine)
├── lang/                   ← Translation directories (lang/en/app.php, lang/ar/app.php)
├── public/
│   ├── index.php           ← Front controller entry point (HTTP & FrankenPHP worker)
│   └── .htaccess           ← Apache rewrite engine rules
├── routes/
│   ├── web.php             ← Public visitor web routes
│   ├── admin.php           ← Protected backoffice administration routes
│   └── api.php             ← Stateless JSON endpoints
├── src/                    ← Your Application (App\ namespace)
│   ├── Controllers/        ← Controllers extending Spartan\Controller
│   ├── Models/             ← Active Record Models extending Spartan\Model
│   ├── Services/           ← Decoupled multi-model business logic classes
│   ├── Listeners/          ← Event handlers (handle(mixed $payload): void)
│   ├── Events/             ← Event name constants
│   ├── Middlewares/        ← Custom middlewares extending Spartan\Middleware
│   └── Views/              ← Blade templates (.blade.php) and layouts (.blade.php)
│       └── layouts/        ← Master layout wrappers (main.blade.php, admin.blade.php)
├── storage/
│   ├── cache/              ← File driver cache & config cache
│   ├── logs/               ← Daily rotated PSR-3 log files
│   └── views/              ← Compiled Blade PHP templates cache
└── spartan                 ← CLI Console runner (Executable script)
```

---

## 4. The Mandatory 5-Step Feature Addition Workflow

When implementing **any** feature, you must strictly follow this sequence from bottom to top:

```
[ 1. routes/ ]       → Register route path, HTTP method, and attach middleware
       ↓
[ 2. src/Models/ ]   → Define Active Record Model, table name, relationships, QueryBuilder methods
       ↓
[ 3. src/Services/ ] → (Optional) Add Service class if logic spans multiple models or has side-effects
       ↓
[ 4. src/Controllers/ ] → Orchestrate: accept Request → validate → invoke Model/Service → dispatch Event → render View
       ↓
[ 5. src/Views/ ]    → Pure presentation: extend layout, loop data, render with {{ $var }}, @csrf
```

---

## 5. Dependency Injection Container (`Spartan\Container`)

Spartan provides a zero-dependency DI Container with automatic Reflection resolution and constructor metadata caching:

```php
use Spartan\Application;

// 1. Factory Binding (new instance on every make):
Application::$app->container->bind(MailService::class, fn() => new SmtpMailer(config('mail')));

// 2. Singleton Binding (cached single instance):
Application::$app->container->singleton(PaymentService::class, fn() => new StripeGateway());

// 3. Instance Binding (bind pre-constructed instance):
Application::$app->container->instance(CustomClient::class, $client);

// 4. Automatic Resolution (via Controller or Container):
$mailer = $this->make(MailService::class);
$mailer = Application::$app->container->make(MailService::class);
```

---

## 6. Controllers Layer (`src/Controllers/`)

Every controller class must:
1. Include `declare(strict_types=1);` as the first line.
2. Extend `Spartan\Controller`.
3. Call `parent::__construct()` if implementing a custom constructor.

```php
namespace App\Controllers;

use Spartan\Controller;
use App\Models\Order;
use App\Services\PaymentService;

class OrderController extends Controller
{
    // DI container automatically resolves constructor dependencies
    public function __construct(private PaymentService $payment)
    {
        parent::__construct();
    }

    public function index(): string
    {
        // Available core properties:
        $request  = $this->request;   // Spartan\Request instance
        $response = $this->response;  // Spartan\Response instance
        $session  = $this->session;   // Spartan\SessionInterface instance
        $auth     = $this->auth;      // Spartan\AuthInterface instance

        $orders = (new Order)->getActiveOrders();

        // Render full Blade view with layout:
        return $this->render('orders/index', [
            'title'  => 'Orders List',
            'orders' => $orders,
        ]);
    }

    public function partial(): string
    {
        // Render HTMX / AJAX partial without master layout:
        return $this->renderViewOnly('orders/partials/row', ['item' => $data]);
    }

    public function store(): void
    {
        $v = $this->validate($this->request->getBody(), [
            'item_id'  => 'required|integer',
            'quantity' => 'required|min:1',
        ]);

        if ($v->fails()) {
            $this->session->setFlash('error', 'Please fix form errors.');
            $this->redirect('/orders/create');
            return;
        }

        $this->event('order.placed', ['order_id' => 101]);
        $this->session->setFlash('success', 'Order created successfully!');
        $this->redirect('/orders');
    }

    public function apiData(): void
    {
        $this->json(['status' => 'ok', 'timestamp' => time()]);
    }
}
```

---

## 7. Request & Response API

### Reading Inputs (`Spartan\Request`)
```php
$this->request->get('search');         // Reads $_GET['search']
$this->request->post('email');         // Reads $_POST['email'] or JSON body
$this->request->input('name');         // Reads POST, falls back to GET
$this->request->getParam('name');      // Alias for input()
$this->request->getBody();             // Returns entire sanitized input array
$this->request->file('avatar');        // Returns uploaded file metadata array
$this->request->header('User-Agent');  // Resolves HTTP headers
$this->request->isAjax();              // True if X-Requested-With: XMLHttpRequest
$this->request->isPost();              // True if HTTP POST
$this->request->isSecure();            // True if HTTPS / SSL
$this->request->getClientIp();         // Client IP (resolves trusted proxies safely)
```

### Response Controls (`Spartan\Response`)
```php
$this->response->setStatusCode(404);
$this->response->setHeader('X-Custom-Header', 'Value');
$this->response->json(['key' => 'value'], 200);
$this->response->noContent();          // 204 No Content
$this->response->cookie('theme', 'dark', expire: time() + 86400, secure: true, httponly: true);
$this->response->deleteCookie('theme');
$this->redirect('/path');              // Protected against open-redirect attacks
```

---

## 8. Models & QueryBuilder Layer (`src/Models/`)

### Defining a Model
```php
namespace App\Models;

use Spartan\Model;
use Spartan\RelationQuery;

class Product extends Model
{
    protected string $table = 'products';
    protected bool $timestamps = true; // Auto-stamps created_at & updated_at

    public function category(): RelationQuery
    {
        return $this->belongsTo(Category::class, foreignKey: 'category_id');
    }

    public function reviews(): RelationQuery
    {
        return $this->hasMany(Review::class, foreignKey: 'product_id');
    }
}
```

### Fluent QueryBuilder Operations (`$this->table()`)

```php
// Fetching records:
$products = $this->table()->get();
$first    = $this->table()->where('active', 1)->first();
$rawArray = $this->table()->find(5);
$model    = (new Product)->findInstance(5);
$bySlug   = (new Product)->findInstanceBy('slug', 'tv');

// Where filtering & Operators:
$active = $this->table()
    ->where('active', 1)
    ->where('price', 100, '>=')
    ->where('status', ['published', 'featured'], 'IN')
    ->select('id', 'name', 'price')
    ->orderBy('created_at', 'DESC')
    ->limit(10)
    ->offset(0)
    ->get();

// Aggregates & Pagination:
$count     = $this->table()->where('active', 1)->count();
$exists    = $this->table()->where('sku', $sku)->exists();
$paginated = $this->table()->paginate(perPage: 15, page: 1);

// Joins:
$this->table('orders')
    ->join('users', 'orders.user_id', 'users.id')
    ->leftJoin('coupons', 'orders.coupon_id', '=', 'coupons.id')
    ->select('orders.id', 'users.name')
    ->get();

// Write operations (Strict write guard requires where() on update/delete):
$insertId     = $this->table()->insert(['name' => 'Laptop', 'price' => 999]);
$affectedRows = $this->table()->where('id', 5)->update(['price' => 899]);
$deletedRows  = $this->table()->where('id', 5)->delete();
$this->table()->truncate(); // Intentional complete wipe

// Atomic Transactions:
$this->transaction(function ($model) {
    $model->table('orders')->insert(['total' => 500]);
    $model->table('inventories')->where('sku', 'ITEM-1')->update(['stock' => 10]);
});
```

---

## 9. Multi-Database & Read/Write Splitting (`ConnectionManager`)

Spartan includes native multi-database management and Master/Read-Replica splitting:

* **Single DB Mode**: All queries route through `'default'`.
* **Read/Write Splitting Mode (`DB_READ_WRITE_SPLIT=true`)**:
  * `SELECT` queries automatically execute on a random read replica (`DB_READ_HOST_1`, `DB_READ_HOST_2`).
  * `INSERT`, `UPDATE`, `DELETE`, and `transaction()` operations always execute on the primary write database.
* **Named Connections**:
  ```php
  $pdo = Application::$app->dbManager->connection('analytics');
  ```

---

## 10. Model Relationships & Zero $N+1$ Eager Loading

```php
// Single record:
$orders = (new User)->orders()->for($user);

// EAGER LOADING (2 queries total, never N+1):
$users = (new User)->table()->where('active', 1)->get();
$users = (new User)->orders()->loadFor($users, as: 'orders');

// Or directly on the model query:
$users = User::query()->with(['orders'])->get();
```

---

## 11. Validation & FormRequests

### Complete Validation Rule Vocabulary
* `required`: Field must be present and not empty.
* `string` / `integer` / `boolean` / `numeric` / `alpha` / `alpha_num`: Type checks.
* `email`: Valid email format.
* `url`: Valid URL structure.
* `date`: Valid parseable date.
* `min:N` / `max:N`: Minimum/maximum length (strings) or values (numerics).
* `confirmed`: Requires matching `{field}_confirmation` input (e.g. `password_confirmation`).
* `in:a,b,c`: Value must match one of the allowed options.
* `nullable`: Allows null or empty string to pass.
* `regex:/pattern/`: Matches against regular expression.
* `unique:table,column`: Ensures value does not already exist in the database.

### FormRequests (`Spartan\FormRequest`)
```php
namespace App\Controllers\Requests;

use Spartan\FormRequest;

class StoreProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->session->get('role') === 'admin';
    }

    public function rules(): array
    {
        return [
            'name'  => 'required|string|min:3',
            'price' => 'required|numeric|min:0.01',
        ];
    }
}

// Controller auto-injection & auto-validation:
class ProductController extends Controller
{
    public function store(StoreProductRequest $request): void
    {
        $data = $request->getBody(); // Guaranteed authorized and valid
    }
}
```

---

## 12. Complete Spartan Blade Directives & View Development Guide (`.blade.php`)

Spartan includes its own native, zero-dependency Blade compiler in `Spartan\View` that compiles `.blade.php` templates into cached PHP in `storage/views/`.

### 12.1 Complete Directive Reference Table

| Directive | Compiled Output | Purpose |
|---|---|---|
| `{{ $var }}` | `<?= htmlspecialchars($var, ENT_QUOTES, 'UTF-8') ?>` | Safely escapes variables (XSS-proof). |
| `{!! $raw !!}` | `<?= $raw ?>` | Renders raw, unescaped HTML content. |
| `@extends('layouts.name')` | `$this->extend('layouts.name')` | Declares the master layout template. |
| `@section('name') ... @endsection` | `$this->startSection('name') ...` | Defines a named content section block. |
| `@section('name', expression)` | `$this->sections['name'] = expression;` | Inline short section assignment. |
| `@yield('name', 'Default')` | `echo $this->yieldContent('name')` | Yields content slot inside a layout. |
| `@include('partials.card', $params)` | `echo $this->include('partials.card', ...)` | Includes a sub-template with local variables. |
| `@csrf` | `<input type="hidden" name="_csrf" value="...">` | Injects the CSRF security input token. |
| `@flash('key') ... @endflash` | `if($flashMsg = $this->flash('key')):` | Renders temporary session flash alert. |
| `@if(...) ... @elseif(...) ... @else ... @endif` | `if(...): ... elseif(...): ... else: ... endif;` | Conditional logic statements. |
| `@empty($arr) ... @endempty` | `if(empty($arr)): ... endif;` | Executes when a variable is empty. |
| `@foreach($items as $item) ... @endforeach` | `foreach(...): ... endforeach;` | Iteration loop over collections. |
| `@for(...) ... @endfor` | `for(...): ... endfor;` | Standard numeric for loop. |
| `@while(...) ... @endwhile` | `while(...): ... endwhile;` | Standard while condition loop. |
| `@selected($condition)` | `selected="selected"` | Form `<option>` selected attribute helper. |
| `@checked($condition)` | `checked="checked"` | Form `<input checkbox>` checked helper. |
| `@disabled($condition)` | `disabled="disabled"` | Form input disabled attribute helper. |
| `@can('ability', $model) ... @endcan` | `if(\Spartan\Gate::check(...)):` | Evaluates Gate authorization policies. |
| `@cannot('ability', $model) ... @endcannot` | `if(\Spartan\Gate::denies(...)):` | Evaluates Gate authorization denial. |
| `@role('admin') ... @endrole` | `if($user->hasRole('admin')):` | Direct RBAC role checks in views. |
| `@lang('app.welcome')` | `echo htmlspecialchars(trans('app.welcome'))` | Localized translation string output. |

---

### 12.2 Practical View Development Patterns in Spartan Blade

#### Pattern A: Building a Master Layout (`src/Views/layouts/main.blade.php`)
```blade
<!DOCTYPE html>
<html lang="{{ current_locale() }}" @if(is_rtl()) dir="rtl" @endif>
<head>
    <meta charset="UTF-8">
    <title>@yield('title', 'Default Title') — {{ config('app.name') }}</title>
    <link rel="stylesheet" href="{{ asset('css/style.css') }}">
</head>
<body>
    {{-- Global Flash Messages --}}
    @flash('success')
        <div class="alert alert-success">{{ $flashMsg }}</div>
    @endflash

    @flash('error')
        <div class="alert alert-danger">{{ $flashMsg }}</div>
    @endflash

    {{-- Header / Navigation --}}
    <nav>
        <a href="/">Home</a>
        @auth
            <a href="/profile">Profile</a>
            @role('admin')
                <a href="/admin">Backoffice</a>
            @endrole
        @endauth
    </nav>

    {{-- Main Content Slot --}}
    <main>
        @yield('content')
    </main>

    {{-- Optional Scripts Section --}}
    @yield('scripts')
</body>
</html>
```

#### Pattern B: Creating a Child Page View (`src/Views/products/index.blade.php`)
```blade
@extends('layouts.main')

@section('title', 'Products Catalog')

@section('content')
    <h1>{{ $categoryName }} ({{ count($products) }})</h1>

    @if(empty($products))
        <p>No products found in this category.</p>
    @else
        <div class="product-grid">
            @foreach($products as $product)
                @include('products.partials.card', ['product' => $product])
            @endforeach
        </div>
    @endif
@endsection

@section('scripts')
    <script src="{{ asset('js/products.js') }}"></script>
@endsection
```

#### Pattern C: Creating Form Views with Validation & Helpers (`src/Views/products/create.blade.php`)
```blade
@extends('layouts.main')

@section('title', 'Add New Product')

@section('content')
    <form method="POST" action="/products">
        @csrf

        {{-- Text Input --}}
        <div>
            <label>Product Name</label>
            <input type="text" name="name" value="{{ $old['name'] ?? '' }}">
            @if(!empty($errors['name']))
                <span class="error">{{ $errors['name'][0] }}</span>
            @endif
        </div>

        {{-- Select Dropdown with @selected --}}
        <div>
            <label>Category</label>
            <select name="category_id">
                @foreach($categories as $cat)
                    <option value="{{ $cat->id }}" @selected(($old['category_id'] ?? '') == $cat->id)>
                        {{ $cat->name }}
                    </option>
                @endforeach
            </select>
        </div>

        {{-- Checkbox with @checked --}}
        <div>
            <label>
                <input type="checkbox" name="is_featured" value="1" @checked(!empty($old['is_featured']))>
                Featured Item
            </label>
        </div>

        {{-- Submit Button with @disabled --}}
        <button type="submit" @disabled(!empty($isReadOnly))>Save Product</button>
    </form>
@endsection
```

#### Pattern D: Sub-Template Partial (`src/Views/products/partials/card.blade.php`)
```blade
<div class="product-card">
    <h3>{{ $product->name }}</h3>
    <p>Price: ${{ number_format($product->price, 2) }}</p>

    @can('edit_product', $product)
        <a href="/products/{{ $product->id }}/edit">Edit</a>
    @endcan

    @cannot('purchase', $product)
        <span class="badge out-of-stock">Out of Stock</span>
    @endcannot
</div>
```

#### Pattern E: HTMX / AJAX Partials (No Master Layout)
In your Controller:
```php
public function liveSearch(): string
{
    $results = (new Product)->search($this->request->get('q'));
    // Render only the partial template, completely skipping the master layout wrapper:
    return $this->renderViewOnly('products.partials.search_results', ['results' => $results]);
}
```
In your View (`src/Views/products/partials/search_results.blade.php`):
```blade
<ul>
    @foreach($results as $item)
        <li>{{ $item->name }} - ${{ $item->price }}</li>
    @endforeach
</ul>
```

---

## 13. Routing & Middlewares (`Spartan\Router`, `Spartan\Middleware`)

```php
// 1. routes/web.php — Public routes
$app->router->get('/', [HomeController::class, 'index']);
$app->router->get('/items/{id}', [ItemController::class, 'show']);
$app->router->get('/users/{id:\d+}', [UserController::class, 'show']); // Regex constraint

// 2. routes/admin.php — Protected routes with Middleware & Rate Limiting
$app->router->get('/admin', [AdminController::class, 'index'], [
    \Spartan\Middlewares\AuthMiddleware::class,
    'rate_limit:120,60' // 120 requests per 60 seconds
]);

// 3. Middleware Groups & Aliases
$app->router->middlewareGroup('admin_guard', [
    \Spartan\Middlewares\SecurityHeadersMiddleware::class,
    \Spartan\Middlewares\AuthMiddleware::class,
]);
$app->router->aliasMiddleware('auth', \Spartan\Middlewares\AuthMiddleware::class);

// 4. CSRF Exclusions (Webhooks / External APIs)
$app->router->excludeCsrf('/api/stripe/webhook', '/api/v1/*');

// 5. Route Caching (compiled route performance)
$app->router->saveCache();
$app->router->loadCache();
```

---

## 14. Authorization (RBAC) & PHP 8.1 Attributes

### Controller Action Attributes
```php
namespace App\Controllers\Admin;

use Spartan\Controller;
use Spartan\Attributes\RequireRole;
use Spartan\Attributes\RequirePermission;

class SettingsController extends Controller
{
    #[RequireRole('admin')]
    #[RequirePermission('manage_settings')]
    public function update(): void
    {
        // Enforced via Reflection before method execution (403 Forbidden if denied)
    }
}
```

### User Model Authorization Trait (`Spartan\Traits\HasAuthorization`)
```php
class User extends Model {
    use HasAuthorization;
}

$user->hasRole('admin', 'superadmin'); // bool
$user->hasPermission('publish_post');   // bool
$user->assignRole('editor');
$user->removeRole('editor');
$user->getRoles();                     // ['admin', 'editor']
$user->getPermissions();               // ['manage_users', 'publish_post']
```

---

## 15. Tagged Caching Engine (`Spartan\TaggedCache`)

```php
use Spartan\Cache;

// Cache with Tag grouping:
Cache::tags(['products', 'catalog'])->put('top_10', $data, 3600);
Cache::tags(['products'])->remember('all', 3600, fn() => Product::all());

// Invalidate all entries tagged 'products' at once:
Cache::tags(['products'])->flush();

// Basic Cache:
Cache::put('key', $value, 600);
Cache::get('key', 'default');
Cache::forget('key');
Cache::flush();
```

---

## 16. Events, Listeners & Async Job Queue

```php
// Register sync & async background listeners:
Application::$app->events->listen('order.created', UpdateStockListener::class); // Sync
Application::$app->events->listen('order.created', SendEmailListener::class, async: true, maxAttempts: 3, onFailure: 'retry');

// Dispatch event:
$this->event('order.created', ['order_id' => 101]);

// Queue Driver Configuration:
// Set QUEUE_DRIVER=database or QUEUE_DRIVER=redis in .env
```

---

## 17. The Complete 13 Spartan CLI Commands (`./spartan`)

| CLI Command | Purpose |
|---|---|
| `php spartan migrate` | Execute all outstanding database migrations. |
| `php spartan migrate:rollback [steps]` | Roll back the last N migration batches (default 1). |
| `php spartan migrate:status` | View status and batch number of every migration. |
| `php spartan migrate:reset` | Roll back all migrations completely. |
| `php spartan migrate:fresh` | Drop all tables and re-run all migrations from scratch. |
| `php spartan db:seed` | Seed database using `database/seed.sql`. |
| `php spartan worker` | Execute a single pass of the background job queue (for Cron). |
| `php spartan worker --loop` | Run continuous background queue daemon (polling every 5s). |
| `php spartan health` | Run full system health diagnostic (PHP, DB, Memory, Permissions, OPcache). |
| `php spartan config:cache` | Compile and cache `.env` & config array to `storage/cache/config.php`. |
| `php spartan config:clear` | Delete cached configuration. |
| `php spartan lang:list` | List all available locales and active fallback/RTL badges. |
| `php spartan lang:check` | Compare all language files and report missing translation keys. |

---

## 18. System Health Diagnostics (`Spartan\HealthCheck`)

Run system health audits via CLI or programmatically:
```php
$health = new \Spartan\HealthCheck();
$report = $health->run();
```
Output includes:
- **`status`**: `'healthy'` | `'unhealthy'`
- **`php_version`**: Active PHP runtime
- **`database`**: Connection latency & status
- **`storage_writable`**: Permission verification on `storage/`
- **`memory_usage`**: Peak memory consumption
- **`opcache_enabled`**: Bytecode caching status

---

## 19. Production & Security Checklist

- [x] Every PHP file begins with `declare(strict_types=1);`.
- [x] All database queries use `$this->table()` QueryBuilder — zero concatenated SQL strings.
- [x] Every form includes `@csrf` and every non-GET endpoint validates CSRF tokens.
- [x] All user variables rendered in views use `{{ $var }}` for automatic HTML escaping.
- [x] Eager loading is used on all collections to eliminate $N+1$ query loops.
- [x] Sensitive actions call `$this->session->regenerate()` upon login and role elevation.
- [x] External URLs are redirected strictly through `$this->redirect()` with open-redirect guards.
