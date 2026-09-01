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

// 5. Form Input Repopulation
old('email', 'default@example.com');       // Reads flashed input or request body safely

// 6. Path Resolution (Spartan\Paths)
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
| `{{-- comment --}}` | ` ` (stripped during compilation) | Blade comments (never rendered in HTML). |
| `@extends('layouts.name')` | `$this->extend('layouts.name')` | Declares the master layout template. |
| `@section('name') ... @endsection` | `$this->startSection('name') ...` | Defines a named content section block. |
| `@section('name', expression)` | `$this->sections['name'] = expression;` | Inline short section assignment. |
| `@yield('name', 'Default')` | `echo $this->yieldContent('name')` | Yields content slot inside a layout. |
| `@include('partials.card', $params)` | `echo $this->include('partials.card', ...)` | Includes a sub-template with local variables. |
| `@csrf` | `<input type="hidden" name="_csrf" value="...">` | Injects the CSRF security input token. |
| `@method('PUT')` | `<input type="hidden" name="_method" value="PUT">` | Form HTTP method spoofing helper. |
| `@flash('key') ... @endflash` | `if($flashMsg = $this->flash('key')):` | Renders temporary session flash alert. |
| `@if(...) ... @elseif(...) ... @else ... @endif` | `if(...): ... elseif(...): ... else: ... endif;` | Conditional logic statements. |
| `@empty($arr) ... @endempty` | `if(empty($arr)): ... endif;` | Executes when a variable is empty. |
| `@foreach($items as $item) ... @endforeach` | `foreach(...): ... endforeach;` | Iteration loop over collections. |
| `@for(...) ... @endfor` | `for(...): ... endfor;` | Standard numeric for loop. |
| `@while(...) ... @endwhile` | `while(...): ... endwhile;` | Standard while condition loop. |
| `@selected($condition)` | `selected="selected"` | Form `<option>` selected attribute helper. |
| `@checked($condition)` | `checked="checked"` | Form `<input checkbox>` checked helper. |
| `@disabled($condition)` | `disabled="disabled"` | Form input disabled attribute helper. |
| `@auth ... @endauth` | `if(\Spartan\Gate::resolveUser() !== null):` | Renders block only for authenticated users. |
| `@guest ... @endguest` | `if(\Spartan\Gate::resolveUser() === null):` | Renders block only for guest visitors. |
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

### 12.3 Official UI Layout & Wireframing Recommendation: `pure-responsive-div-builder`

> **ZERO BLOAT • ZERO DIV BURSTING • 100% CONTENT-AGNOSTIC WIREFRAMING**  
> - **GitHub Repository**: [`github.com/blackmoon87/pure-responsive-div-builder`](https://github.com/blackmoon87/pure-responsive-div-builder)  
> - **Live Web Builder**: [`blackmoon87.github.io/pure-responsive-div-builder`](https://blackmoon87.github.io/pure-responsive-div-builder/)  
> - **Local Workspace**: `htmlCreator/`

When designing responsive layouts, backoffice dashboards, or view skeletons for Spartan Blade, **do not rely on heavy Figma-to-HTML converters**. Instead, use **`pure-responsive-div-builder` (`htmlCreator`)**.

#### Why `pure-responsive-div-builder` is the Spartan Standard
1. **100% Pure Architecture**: Generates clean, predictable CSS Grid and Flexbox structures without injecting inline garbage or framework bloat.
2. **Stress-Tested Responsiveness**: Emits triple-breakpoint CSS (Desktop, Tablet ≤992px, Mobile ≤576px) with automatic `min-width: 0`, neutral media-query diffing, and anti-burst reset rules.
3. **Native RTL Support**: Built-in `dir="rtl"` and logical flow for Arabic and Hebrew views.
4. **AI-Agent & MCP Native**: Includes a 21-tool Model Context Protocol (MCP) server under `htmlCreator/mcp-server/` so AI coding agents can construct, inspect, and export page skeletons programmatically.

#### The 3-Step UI Development Workflow

```
[ Step 1: Wireframe ]   → Build structural skeleton via htmlCreator UI or MCP tool (build_tree / export_full)
           ↓
[ Step 2: Export CSS ]  → Place generated CSS into public/css/pages/{module}.css
           ↓
[ Step 3: Inject Blade] → Drop HTML into src/Views/{module}/index.blade.php & add @extends, @csrf, @foreach, old()
```

#### Practical Example: From `htmlCreator` Skeleton to Spartan Blade

**1. Generated CSS (`public/css/dashboard.css`):**
```css
.dash-root { display: flex; flex-direction: column; min-height: 100vh; }
.dash-body { display: grid; grid-template-columns: 260px 1fr; gap: 20px; padding: 20px; }
.dash-content { display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; }
.metric-card { background: #1d2737; border-radius: 8px; padding: 16px; min-width: 0; }

@media (max-width: 992px) {
  .dash-body { grid-template-columns: 1fr; }
  .dash-content { grid-template-columns: repeat(2, 1fr); }
}
@media (max-width: 576px) {
  .dash-content { grid-template-columns: 1fr; }
}
```

**2. Assembled Spartan Blade View (`src/Views/dashboard/index.blade.php`):**
```blade
@extends('layouts.main')

@section('title', 'Admin Dashboard')

@section('content')
<div class="dash-root">
    <div class="dash-body">
        <div class="dash-sidebar">
            @include('admin.partials.sidebar')
        </div>

        <div class="dash-content">
            @foreach($metrics as $metric)
                <div class="metric-card">
                    <h4>{{ $metric->title }}</h4>
                    <p class="stat-number">{{ number_format($metric->value) }}</p>
                </div>
            @endforeach
        </div>
    </div>
</div>
@endsection
```

---

### 12.4 Zero-Deformation Responsive Law (Guaranteed Layout Integrity)

> **ZERO VIEW DEFORMATION • ZERO HORIZONTAL SCROLL • NATIVE 320px–4K STABILITY**  
> Every layout rendered in Spartan must follow these 5 mathematical anti-deformation rules to guarantee visual perfection across mobile, tablet, and desktop without layout breaks.

#### 1. The Anti-Burst Foundation (Mandatory Reset)
Never allow images, flex children, code snippets, or tables to widen the viewport or burst their containers:

```css
*, *::before, *::after {
  box-sizing: border-box;
}

html, body {
  width: 100%;
  max-width: 100%;
  overflow-x: hidden; /* Guaranteed no horizontal scroll */
}

/* Prevents Flex and Grid children from blowing out containers on long strings/URLs */
.grid > *, .flex > *, [class*="col-"] {
  min-width: 0;
  overflow-wrap: break-word;
}

/* Fluid media rule */
img, video, canvas, svg {
  max-width: 100%;
  height: auto;
  display: block;
}
```

#### 2. The Bulletproof Auto-Grid (Zero Media Queries)
Uses `min(100%, min-size)` so columns automatically scale down on tiny 320px phones without bursting:

```css
.auto-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(min(100%, var(--min-col, 280px)), 1fr));
  gap: var(--gap, 1.5rem);
}
```

#### 3. Container Queries for Context-Aware Components (`@container`)
Components adapt to their parent container rather than the screen viewport. This ensures cards look perfect whether inside a narrow 300px sidebar or a 1200px main area:

```css
.component-container {
  container-type: inline-size;
}

@container (max-width: 480px) {
  .responsive-card {
    flex-direction: column;
    padding: 12px;
  }
}
```

#### 4. Fluid Typography & Spacing (No Jarring Breakpoint Jumps)
```css
:root {
  --font-base: clamp(0.9375rem, 0.88rem + 0.3vw, 1.0625rem); /* 15px -> 17px */
  --font-h1: clamp(1.75rem, 1.3rem + 2vw, 2.75rem);          /* 28px -> 44px */
  --font-h2: clamp(1.35rem, 1.1rem + 1.2vw, 2rem);           /* 21px -> 32px */
  --pad-page: clamp(1rem, 0.75rem + 1.2vw, 2.5rem);
}
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

## 17. The Complete 15 Spartan CLI Commands (`./spartan`)

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
| `php spartan view:clear` | Clear the compiled Blade view cache in `storage/views/`. |
| `php spartan route:list` | Print a formatted table of all registered routes and middleware. |
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

[styling] 
# CSS Styling Governance & Precedence Order of Flow

> **CRITICAL DEVELOPER OVERRIDE LAW**:  
> When developing a site, if the developer or user provides custom styling instructions, design tokens, external CSS files, custom branding, or specific page designs, **IGNORE the default Spartan Glass/Theme styling** and strictly follow the **Precedence Order of Flow** below.

---

## 1. CSS Precedence Order of Flow (Priority Hierarchy)

When constructing, styling, or modifying views in Spartan, resolve styles in this exact order (Highest Priority &rarr; Lowest Priority):

```
┌─────────────────────────────────────────────────────────────────────────┐
│ 1. DEVELOPER / USER EXPLICIT STYLES (HIGHEST PRIORITY)                  │
│    Custom themes, brand guidelines, custom CSS files, UI requirements   │
│    → COMPLETELY OVERRIDES ALL DEFAULT SPARTAN STYLING                   │
├─────────────────────────────────────────────────────────────────────────┤
│ 2. WIREFRAME & STRUCTURAL SKELETON (htmlCreator / CSS Grid / Flexbox)   │
│    Layout structure exported from pure-responsive-div-builder           │
│    (public/css/pages/*.css)                                             │
├─────────────────────────────────────────────────────────────────────────┤
│ 3. ZERO-DEFORMATION SAFETY LAYER (Section 12.4)                         │
│    box-sizing: border-box, min-width: 0, overflow-x: hidden, clamp()    │
│    → ALWAYS APPLIED to guarantee 320px–4K layout stability without burst│
├─────────────────────────────────────────────────────────────────────────┤
│ 4. OPTIONAL STYLE PRESETS (Mutually Exclusive — Pick One Per Project)   │
│    a) GLASS-OVER-SCENE — frosted panel system over a painted CSS scene  │
│    b) 3D ISOMETRIC STACKED BLOCKS — chunky toy-like layered tower       │
│    Applied ONLY when the user explicitly requests one of these presets  │
├─────────────────────────────────────────────────────────────────────────┤
│ 5. DEFAULT SPARTAN BASE FALLBACK (LOWEST PRIORITY)                      │
│    Clean minimal foundation applied only if zero styles are specified   │
└─────────────────────────────────────────────────────────────────────────┘
```

### Flow Execution Rules for Developers & AI Agents:
1. **Developer Explicit Intent Always Wins**: If custom colors, stylesheets, or layouts are provided, bypass all default framework styling presets and apply the developer's styling directly.
2. **Modular File Flow Structure**:
   - `public/css/reset.css` &rarr; Section 12.4 anti-burst foundation and document reset.
   - `public/css/app.css` &rarr; Global design tokens, typography, navigation, and common components.
   - `public/css/pages/{module}.css` &rarr; Module-specific page grids, tables, and views.
3. **Non-Negotiable Safety**: Whatever visual styling the developer chooses, the structural reset (`min-width: 0`, `overflow-wrap: break-word`, `overflow-x: hidden`) must remain active so views never horizontally deform or burst on mobile devices.

---

# Glass-Over-Scene UI Style (Optional Preset)

Apply this style only when the user or project requests the frosted glass theme. It is a frosted-panel system layered over a full-bleed painted background scene.

---

## Interaction protocol

Ask the user exactly **one** question before writing code:

> Which color pattern? sky-blue / sunshine / dark-night / red-orange / meadow-green / deep-ocean

Take the answer, load the matching preset from **Palettes**, write the code. Do not ask anything else. Do not ask about fonts, layout, sections, or framework unless the user's request is impossible without it.

If the user names a pattern not on the list, build the preset yourself using the rules in **Building a new palette**.

---

## Non-negotiable rules

These are corrections for failures that occur every time this style is built naively.

1. **Panel tint follows the scene, never the reverse.**
   - Light/bright scene (sky, sun, sand, snow) → panels are a **dark tint** of the scene's dominant hue at 30–40% alpha. White panels on a bright scene wash out and text becomes unreadable.
   - Dark scene (night, deep ocean, storm) → panels are a **white tint** at 8–14% alpha.
2. **Every panel needs two edges.** One light inner border and one dark outer hairline:
   ```css
   border: 1px solid var(--line);
   box-shadow: 0 0 0 1px var(--edge-dark), 0 16px 34px var(--drop), inset 0 1px 0 rgba(255,255,255,.28);
   ```
   A single white border disappears wherever the scene behind it is bright. This is the most common defect.
3. **Blur budget.** `backdrop-filter: blur(12px) saturate(1.15)`. Do not exceed 16px. Do not exceed saturate 1.25. Higher values look smeared, not frosted.
4. **The scene needs a veil.** A full-size overlay layer at 16–34% of the scene's darkest color, sitting above the scene layers and below content. Without it, nothing is legible on the bright band.
5. **Mobile menu is opaque.** Use `--solid` (92% alpha), never the panel token. A transparent dropdown lets the hero text bleed through it.
6. **Text over a scene always carries a shadow:** `text-shadow: 0 1px 3px <dark tint at .55>`. Tint the shadow with the scene hue; pure black over warm scenes goes muddy.
7. **`overflow-x: hidden` on `html, body`** and `overflow-wrap: break-word` on headings. Fluid `clamp()` headings otherwise widen the document and clip the sticky navbar off the right edge.
8. **Stacking.** Scene is `position: fixed; z-index: 0`. Content wrappers get `position: relative; z-index: 1`. Sticky nav gets `z-index: 100`.
9. **Inputs are not fully transparent.** Give them the dark tint at ~28% alpha. Fully transparent inputs are invisible against a busy scene.
10. **Gradient only on the primary button.** Every other button is a flat translucent panel with the same border treatment. More than one gradient per screen kills the hierarchy.
11. **No comments in the code.**

---

## Token block

Emit exactly this shape in `:root`, filled from the chosen preset.

```css
:root{
  --panel:        /* base panel fill */
  --panel-2:      /* hover / emphasized panel, +12% alpha */
  --solid:        /* opaque version, 92% alpha — mobile menu, dropdowns */
  --blur:blur(12px) saturate(1.15);
  --line:         /* inner border, white or near-white at .30–.45 */
  --line-soft:    /* --line at ~.70 of its alpha */
  --edge-dark:    /* outer hairline, scene's darkest hue at .28–.35 */
  --drop:         /* drop shadow color, same hue at .28 */
  --text:         /* near-white or near-black body text */
  --muted:        /* --text at .82 */
  --btn-a: --btn-b: --btn-c:  /* 3-stop gradient, light → mid → dark */
  --r:20px; --r-sm:12px;
  --shade:0 1px 3px /* dark tint at .55 */;
}
```

Primary button gradient is always `linear-gradient(100deg, var(--btn-a) 0%, var(--btn-b) 55%, var(--btn-c) 100%)`.

---

## Palettes

### sky-blue
```css
--panel:rgba(8,48,92,.34); --panel-2:rgba(8,48,92,.46); --solid:rgba(9,52,98,.92);
--line:rgba(255,255,255,.42); --line-soft:rgba(255,255,255,.30);
--edge-dark:rgba(4,32,66,.30); --drop:rgba(4,28,58,.28);
--text:#f2f9ff; --muted:rgba(226,240,252,.82);
--btn-a:#bfe6ff; --btn-b:#3d9be8; --btn-c:#0b4f9e;
--shade:0 1px 3px rgba(4,28,58,.55);
```
Scene: vertical ramp `#083a76 → #1163a8 → #2f8bc9 → #6bb0dc → #a9cfe6`, warm sun glow top-right, blurred white cirrus band at 8–36%, hard cloud bank at 46–76%, pale cloud-sea floor from 74%.

### sunshine
```css
--panel:rgba(102,58,10,.30); --panel-2:rgba(102,58,10,.42); --solid:rgba(92,52,8,.92);
--line:rgba(255,248,232,.46); --line-soft:rgba(255,248,232,.32);
--edge-dark:rgba(74,38,4,.32); --drop:rgba(74,38,4,.26);
--text:#fffaf0; --muted:rgba(255,246,232,.84);
--btn-a:#fff2b8; --btn-b:#ffb545; --btn-c:#d97706;
--shade:0 1px 3px rgba(70,36,4,.5);
```
Scene: ramp `#ffd25e → #ffb547 → #f79a3c → #ffe6a8`, large soft sun disc at 62% width centered high, hazy bloom band, wheat-toned floor with fine streak texture.

### dark-night
```css
--panel:rgba(255,255,255,.07); --panel-2:rgba(255,255,255,.13); --solid:rgba(14,16,32,.94);
--line:rgba(255,255,255,.20); --line-soft:rgba(255,255,255,.13);
--edge-dark:rgba(0,0,0,.55); --drop:rgba(0,0,0,.5);
--text:#eef1fb; --muted:rgba(226,231,248,.72);
--btn-a:#c7d2fe; --btn-b:#7c6cf5; --btn-c:#3b1f9e;
--shade:0 1px 3px rgba(0,0,0,.7);
```
Inverted case: white-tint panels, veil at only 10–15%. Scene: ramp `#05070f → #0d1230 → #1a1f4a`, violet aurora radial upper-left, teal radial right, 1px star dots via `radial-gradient` background-size 90px, horizon glow along the bottom.

### red-orange
```css
--panel:rgba(72,14,20,.36); --panel-2:rgba(72,14,20,.48); --solid:rgba(64,12,18,.93);
--line:rgba(255,236,224,.42); --line-soft:rgba(255,236,224,.28);
--edge-dark:rgba(48,6,10,.38); --drop:rgba(48,6,10,.32);
--text:#fff4ee; --muted:rgba(255,236,226,.82);
--btn-a:#ffd9a3; --btn-b:#f2894a; --btn-c:#9e2f36;
--shade:0 1px 3px rgba(45,10,14,.55);
```
Scene: ramp `#1d1436 → #4a2154 → #8e3a5c → #cf5f4e → #f09a52 → #ffd39a`, sun disc at the horizon line, blurred haze band above it, two dune/ridge layers in plum and rust.

### meadow-green
```css
--panel:rgba(20,52,18,.32); --panel-2:rgba(20,52,18,.44); --solid:rgba(18,48,16,.92);
--line:rgba(255,255,255,.42); --line-soft:rgba(255,255,255,.28);
--edge-dark:rgba(10,32,8,.32); --drop:rgba(10,32,8,.28);
--text:#f4fff0; --muted:rgba(238,250,232,.84);
--btn-a:#d7e79a; --btn-b:#8cbf3f; --btn-c:#3f7c14;
--shade:0 1px 3px rgba(10,32,8,.5);
```
Scene: overcast sky ramp `#5f7b93 → #8ba3b4 → #c8d2cd → #e4e3cf`, blurred cloud radials, mid green hills, dark treeline blob cluster on one side, grass field with three overlapping `repeating-linear-gradient` blade layers at 83/91/97deg.

### deep-ocean
```css
--panel:rgba(255,255,255,.09); --panel-2:rgba(255,255,255,.16); --solid:rgba(4,30,46,.94);
--line:rgba(200,246,255,.26); --line-soft:rgba(200,246,255,.16);
--edge-dark:rgba(0,14,24,.5); --drop:rgba(0,14,24,.45);
--text:#eafaff; --muted:rgba(214,244,252,.76);
--btn-a:#a5f3e0; --btn-b:#22a6b3; --btn-c:#06496b;
--shade:0 1px 3px rgba(0,14,24,.6);
```
Inverted case: white-tint panels. Scene: ramp `#02121e → #063348 → #0a5570`, caustic light shafts as skewed `linear-gradient` stripes at low opacity from the top, particulate dots, darker floor.

---

## Building a new palette

If the user names a pattern not listed:

1. Pick the scene's 4–5 stop vertical ramp first. Everything else derives from it.
2. Take the ramp's darkest stop → that hue at 32–36% alpha is `--panel`, at 92% is `--solid`, at 30% is `--edge-dark`, at 55% is the `--shade` color.
3. If the ramp's midpoint luminance is above ~55%, use dark-tint panels. Below that, switch to white-tint panels at 7–14% and drop the veil to 10–15%.
4. Button ramp: pull the scene's brightest warm/light accent for `--btn-a`, its saturated mid for `--btn-b`, a deep shade of the same hue for `--btn-c`. Never sample all three from the background ramp itself — the button must separate from the scene.
5. Text is near-white for dark and mid scenes, near-black (`#12202c`) only if every scene stop is above 70% luminance, in which case flip `--line` to `rgba(0,0,0,.25)` and drop text shadows.

---

## Scene markup

```html
<div class="scene">
  <div class="sky"></div>
  <div class="glow"></div>
  <div class="mid"></div>
  <div class="near"></div>
  <div class="floor"></div>
  <div class="veil"></div>
</div>
```

```css
.scene{position:fixed;inset:0;z-index:0}
.scene > div{position:absolute}
.sky{inset:0}
.glow{inset:0;filter:blur(8px)}
.mid{inset:8% 0 64% 0;filter:blur(12px)}
.near{inset:46% 0 24% 0;filter:blur(7px)}
.floor{inset:74% 0 0 0;filter:blur(4px)}
.veil{inset:0}
```

Build every layer from `linear-gradient` and `radial-gradient` only. No image files, no external URLs — the page must render offline. Use `filter: blur()` for depth and `mask-image: linear-gradient(180deg,transparent,#000 26%)` to fade texture layers into the ground.

To swap in a real photo instead: delete `.scene`, set `body{background:url('photo.jpg') center/cover fixed}`, keep `.veil` as a `body::after`.

---

## Components

All of these share `.panel-bg` (the token block above). Only the differences are listed.

**Navbar** — sticky at `top:12px`, `z-index:100`, height 64px, `border-radius:16px`, fill one step more opaque than `--panel` (~.55). Brand left, links pushed right with `margin-left:auto`. Links are 10px-radius pills that fill with `rgba(255,255,255,.16)` on hover. Below 880px: burger button appears, links become a `position:absolute` dropdown at `top:72px` using `--solid`, toggled by a `.show` class. Burger glyph swaps ☰ / ✕. Menu closes on link click and on outside click.

**Buttons** — height 54px, radius 12px, `rgba(255,255,255,.10)` fill, `--line` border. Primary adds the 3-stop gradient plus `inset 0 1px 0 rgba(255,255,255,.55)`. `.small` variant at height 40px / radius 10px. Disabled at 42% opacity. Press state is `translateY(1px)`, never a scale.

**Cards** — 26px padding, hover `translateY(-4px)` + `--panel-2`. Icon tile 48px, radius 13px, `--line` border, inline SVG stroked `#fff` at 1.7 width. Chips are 999px-radius outlines at 13px.

**Stats row** — one panel, `grid-template-columns: repeat(auto-fit, minmax(160px,1fr))`, cells split by `border-right: 1px solid rgba(255,255,255,.22)`, last cell has none. Below 880px the divider moves to `border-bottom`.

**Price cards** — panel with `padding:0; overflow:hidden`, header strip carrying a `rgba(255,255,255,.07)` fill and a bottom border. Featured variant uses `--panel-2`, a `--btn-a`-tinted header gradient, and the primary button. List items use a CSS-drawn checkmark (`::before`, two borders, `rotate(-45deg)`), not a glyph.

**Form panel** — two-column grid collapsing to one at 880px, divider column border. Inputs 52px tall, dark-tint fill, `--line` border, focus goes to `--btn-a` border plus a denser fill. Checkbox is a hidden `input` with a styled sibling `span` revealing an SVG polyline via `:checked +` and a scale transition.

**Footer** — single panel, `justify-content: space-between`, muted text.

---

## Quality floor

- Responsive to 360px with no horizontal scroll.
- `:focus-visible` outline in `--btn-a`, 3px, offset 3px.
- `@media (prefers-reduced-motion: reduce)` kills all transitions and smooth scroll.
- Body text ≥ 15px, line length under 60ch, contrast checked against the *brightest* region of the scene, not the average.
- One HTML file unless the user asks otherwise. Fonts via one Google Fonts link or system stack.


---

# 3D Isometric Stacked Blocks UI Style (Optional Preset)

Apply this style only when the user or project requests the "3D stacked blocks", "isometric tower", or "layered infographic" look. It is a chunky, toy-like vertical stack of colored blocks representing layered/hierarchical content (architecture diagrams, process flows, model layers, org charts, etc.).

---

## Interaction protocol

Ask the user exactly **one** question before writing code:

> What content does the stack represent? Provide layers as: `number | title | description | examples[]`

Take the answer, auto-assign the rainbow color ramp, build the stack. Do not ask about colors, fonts, icons, or layout unless the user's request is impossible without it.

If the user provides fewer than 3 layers or more than 12, proceed anyway — the system handles 1–N layers.

---

## Non-negotiable rules

These are corrections for failures that occur every time this style is built naively.

1. **Light shadow variant only.** Use a single-direction `box-shadow` with 1–2px offset and low opacity (~0.10–0.20). Never use heavy 5–6px floating shadows. The highlight gradient carries the 3D read, not the shadow.
2. **One shadow per element.** Never stack multiple `box-shadow` layers. One subtle shadow, period.
3. **Highlight bevel on every block.** Each block gets a lighter `linear-gradient` overlay on the top-left quadrant to simulate a top-surface bevel. This is the primary 3D cue.
4. **Icon tray is a flat color shift.** The icon-panel area uses a darker shade of the block's base color as a flat fill — never an `inset` shadow.
5. **Generous radius.** All blocks, tiles, and icon trays use `border-radius: var(--r)` (12–20px). No sharp corners anywhere.
6. **Consistent row height.** Every layer row has the same height within a stack. The gap between rows is 4–8px so the tower reads as a continuous structure.
7. **Number tile is a square.** Same height as the block, 1:1 aspect ratio, same accent color, white numeral, slight depth via the same single shadow.
8. **Examples tile is neutral.** Right-side tile is always light gray (`--ex-bg`), never the accent color. Label text uses the accent color; example terms are dark gray/black.
9. **Color auto-ramp.** Layers auto-cycle through the 7-stop palette. If there are more layers than palette stops, the ramp wraps. Adjacent layers must be visually distinguishable.
10. **Skip decorative base elements unless they reinforce meaning.** The ground plate is always present; decorative icons on the base (building, cloud, cables) are added only when they clarify the content's start/end points.
11. **No comments in the code.**

---

## Token block

Emit exactly this shape in `:root`.

```css
:root {
  --font-stack: 'Montserrat', 'Poppins', system-ui, sans-serif;
  --r: 16px;
  --r-sm: 10px;
  --gap-row: 6px;
  --shadow: 0 2px 4px rgba(0,0,0,.12);
  --highlight: linear-gradient(135deg, rgba(255,255,255,.28) 0%, rgba(255,255,255,0) 50%);
  --ground: #d6d0c8;
  --ground-dark: #b8b0a4;
  --base-plate: linear-gradient(180deg, #c4bdb4 0%, #a89f94 100%);
  --ex-bg: #f0ece6;
  --ex-text: #2a2a2a;
  --grain: url("data:image/svg+xml,%3Csvg viewBox='0 0 256 256' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='.85' numOctaves='4' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)' opacity='.06'/%3E%3C/svg%3E");

  --c1: #7c3aed; --c1-dark: #5b21b6; --c1-light: #a78bfa;
  --c2: #2563eb; --c2-dark: #1d4ed8; --c2-light: #60a5fa;
  --c3: #0891b2; --c3-dark: #0e7490; --c3-light: #22d3ee;
  --c4: #059669; --c4-dark: #047857; --c4-light: #34d399;
  --c5: #ca8a04; --c5-dark: #a16207; --c5-light: #facc15;
  --c6: #ea580c; --c6-dark: #c2410c; --c6-light: #fb923c;
  --c7: #dc2626; --c7-dark: #b91c1c; --c7-light: #f87171;
}
```

---

## Color palette — Rainbow ramp (default)

| Layer | Token | Base | Dark (tray) | Light (highlight) | Hex sample |
|---|---|---|---|---|---|
| 1 | `--c1` | Purple | `#5b21b6` | `#a78bfa` | `#7c3aed` |
| 2 | `--c2` | Blue | `#1d4ed8` | `#60a5fa` | `#2563eb` |
| 3 | `--c3` | Teal | `#0e7490` | `#22d3ee` | `#0891b2` |
| 4 | `--c4` | Green | `#047857` | `#34d399` | `#059669` |
| 5 | `--c5` | Gold | `#a16207` | `#facc15` | `#ca8a04` |
| 6 | `--c6` | Orange | `#c2410c` | `#fb923c` | `#ea580c` |
| 7 | `--c7` | Red | `#b91c1c` | `#f87171` | `#dc2626` |

To swap the entire palette, replace the 7 `--cN` triplets in `:root`. Adjacent layers must differ in hue by at least 30°.

---

## Building a custom palette

If the user names a palette not listed:

1. Pick 5–9 hue stops evenly distributed around the wheel (or clustered for a warm/cool theme).
2. For each stop: base at ~55% lightness, dark at ~38% lightness (icon tray fill), light at ~72% lightness (highlight gradient blend).
3. Ensure WCAG AA contrast for white text on every base color (minimum 4.5:1).
4. Name the tokens `--c1` through `--cN` and document the mapping.

---

## Scene markup

```html
<div class="iso-ground">
  <div class="iso-stack">

    <div class="layer-row" style="--accent:var(--c1);--accent-dark:var(--c1-dark);--accent-light:var(--c1-light)">
      <div class="num-tile">1</div>
      <div class="main-block">
        <div class="block-text">
          <h3 class="block-title">LAYER TITLE</h3>
          <p class="block-desc">Short 2-3 line plain-English description of this layer.</p>
        </div>
        <div class="icon-tray">
          <!-- 2-4 inline SVG icons here -->
        </div>
      </div>
      <div class="ex-tile">
        <span class="ex-label">Examples:</span>
        <span class="ex-list">Term A, Term B, Term C</span>
      </div>
    </div>

    <!-- Repeat .layer-row for each layer -->

  </div>
  <div class="base-plate"></div>
</div>
```

---

## Core CSS

```css
.iso-ground {
  display: flex;
  flex-direction: column;
  align-items: center;
  padding: clamp(1rem, 2vw, 3rem);
  background: var(--ground);
  background-image: var(--grain);
  min-height: 100vh;
}

.iso-stack {
  display: flex;
  flex-direction: column;
  gap: var(--gap-row);
  width: 100%;
  max-width: 1100px;
}

.layer-row {
  display: grid;
  grid-template-columns: 72px 1fr 220px;
  gap: var(--gap-row);
  min-height: 96px;
  align-items: stretch;
}

.num-tile {
  display: flex;
  align-items: center;
  justify-content: center;
  background: var(--accent);
  background-image: var(--highlight);
  color: #fff;
  font: 900 clamp(1.5rem, 2vw, 2.25rem) var(--font-stack);
  border-radius: var(--r);
  box-shadow: var(--shadow);
  aspect-ratio: 1;
  align-self: center;
}

.main-block {
  display: flex;
  align-items: center;
  gap: 1rem;
  background: var(--accent);
  background-image: var(--highlight);
  border-radius: var(--r);
  box-shadow: var(--shadow);
  padding: 1rem 1.25rem;
  overflow: hidden;
}

.block-text {
  flex: 1;
  min-width: 0;
}

.block-title {
  font: 800 clamp(0.95rem, 1.2vw, 1.3rem) var(--font-stack);
  text-transform: uppercase;
  color: #fff;
  letter-spacing: .04em;
  margin: 0 0 .25rem;
  text-shadow: 0 1px 2px rgba(0,0,0,.18);
}

.block-desc {
  font: 400 clamp(0.8rem, 0.9vw, 0.95rem) var(--font-stack);
  color: rgba(255,255,255,.88);
  margin: 0;
  line-height: 1.45;
}

.icon-tray {
  display: flex;
  gap: .6rem;
  align-items: center;
  background: var(--accent-dark);
  border-radius: var(--r-sm);
  padding: .6rem .8rem;
  flex-shrink: 0;
}

.icon-tray svg {
  width: 32px;
  height: 32px;
  fill: #fff;
  filter: drop-shadow(0 1px 1px rgba(0,0,0,.15));
}

.ex-tile {
  display: flex;
  flex-direction: column;
  justify-content: center;
  background: var(--ex-bg);
  background-image: var(--highlight);
  border-radius: var(--r);
  box-shadow: var(--shadow);
  padding: 1rem 1.1rem;
}

.ex-label {
  font: 700 .8rem var(--font-stack);
  color: var(--accent);
  text-transform: uppercase;
  letter-spacing: .03em;
  margin-bottom: .3rem;
}

.ex-list {
  font: 400 .85rem var(--font-stack);
  color: var(--ex-text);
  line-height: 1.4;
}

.base-plate {
  width: 100%;
  max-width: 1100px;
  height: 18px;
  background: var(--base-plate);
  border-radius: 0 0 var(--r) var(--r);
  box-shadow: var(--shadow);
  margin-top: calc(var(--gap-row) * -1);
}
```

---

## Responsive rules

Breakpoints follow the Spartan canonical triple-breakpoint system from Section 12.4:
- Desktop: default (>992px)
- Tablet: ≤992px
- Mobile: ≤576px

```css
@media (max-width: 992px) {
  .layer-row {
    grid-template-columns: 56px 1fr;
  }
  .ex-tile {
    grid-column: 1 / -1;
  }
  .num-tile {
    font-size: 1.3rem;
  }
}

@media (max-width: 576px) {
  .layer-row {
    grid-template-columns: 1fr;
  }
  .num-tile {
    width: 48px;
    height: 48px;
    aspect-ratio: 1;
    justify-self: start;
  }
  .main-block {
    flex-direction: column;
    align-items: stretch;
  }
  .icon-tray {
    flex-wrap: wrap;
  }
}

@media (prefers-reduced-motion: reduce) {
  *, *::before, *::after {
    animation-duration: 0s !important;
    transition-duration: 0s !important;
  }
}
```

---

## Components

All components inherit the token block above. Only the differences are listed.

**Number Tile** — 72px square (56px at ≤992px tablet, 48px at ≤576px mobile), `--accent` fill, white numeral, `var(--highlight)` overlay, `var(--shadow)` depth, `var(--r)` radius. Extra-bold 900 weight.

**Main Block** — flex row containing `.block-text` (flex: 1) and `.icon-tray`. Base fill is `--accent` with the highlight gradient. Title is uppercase bold white, description is muted white at 88% opacity. Shadow is the single subtle `var(--shadow)`. Stacks vertically (flex-direction: column) at ≤576px.

**Icon Tray** — recessed panel inside the main block, filled with `--accent-dark` (flat, no inset shadow). Contains 2–4 inline SVGs at 32px, white fill, with a tiny `drop-shadow`. Radius `var(--r-sm)`. Wraps at ≤576px.

**Examples Tile** — light neutral tile (`--ex-bg`), same row height, `var(--r)` radius. Label in `--accent` color (bold uppercase), example terms in `--ex-text` (regular weight). Spans full width below main block at ≤992px.

**Base Plate** — full-width flat strip below the stack, stone-colored gradient, rounded only on bottom corners. Anchors the tower visually to the ground.

**Interactive press state** — On buttons or clickable blocks, reduce shadow offset to `0 1px 2px rgba(0,0,0,.08)` and add `transform: translateY(1px)` on `:active`. Never use scale transforms.

---

## Data-driven rendering (JS component pattern)

```js
function renderLayerStack(layers) {
  const colors = ['c1','c2','c3','c4','c5','c6','c7'];
  return layers.map((layer, i) => {
    const c = colors[i % colors.length];
    return `
      <div class="layer-row" style="--accent:var(--${c});--accent-dark:var(--${c}-dark);--accent-light:var(--${c}-light)">
        <div class="num-tile">${layer.number}</div>
        <div class="main-block">
          <div class="block-text">
            <h3 class="block-title">${layer.title}</h3>
            <p class="block-desc">${layer.description}</p>
          </div>
          <div class="icon-tray">${layer.icons.join('')}</div>
        </div>
        <div class="ex-tile">
          <span class="ex-label">Examples:</span>
          <span class="ex-list">${layer.examples.join(', ')}</span>
        </div>
      </div>`;
  }).join('');
}
```

Data shape per layer:
```js
{ number: 1, title: 'LAYER NAME', description: '...', color: 'c1', icons: ['<svg>...</svg>'], examples: ['Term A', 'Term B'] }
```

---

## Quality floor

- Responsive to 360px with no horizontal scroll.
- `:focus-visible` outline in `--accent-light`, 3px, offset 3px.
- `@media (prefers-reduced-motion: reduce)` kills all transitions and smooth scroll.
- Body text ≥ 15px, line length under 60ch, white text contrast checked against every `--cN` base color (minimum WCAG AA 4.5:1).
- One HTML file unless the user asks otherwise. Fonts via one Google Fonts link or system stack.
- All blocks maintain equal row height within a single stack instance.
