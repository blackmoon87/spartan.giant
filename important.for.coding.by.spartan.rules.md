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

1. **Hard directional shadow, not soft blur.** Use `box-shadow: 2px 2px 0 var(--shadow-strong)` — a crisp, zero-blur, bottom-right offset. This is what gives the chunky toy-block feel. Soft blurred shadows (`0 2px 4px`) look like generic cards, not stacked blocks.
2. **One shadow per element.** Never stack multiple `box-shadow` layers. One hard shadow, period.
3. **Highlight bevel on every colored block.** Each block gets a lighter `linear-gradient(135deg, rgba(255,255,255,.22), rgba(255,255,255,0) 45%)` overlay to simulate a top-surface bevel. This plus the hard shadow carry the full 3D read.
4. **Icon tray is a flat semi-transparent darken.** Use `background: rgba(0,0,0,.14)` inside the main block — never `inset` shadow, never `--accent-dark`. The tray must look like a recessed shelf inside the block.
5. **Generous radius.** All blocks, tiles, and icon trays use `border-radius: var(--r)` (16px). Icon tray uses `var(--r-sm)` (10px). No sharp corners anywhere.
6. **Main block is a column stack.** Layout is `flex-direction: column` — title on top, description below, icon tray at the bottom. Never side-by-side with the icon tray on desktop.
7. **Number tile stretches full row height.** The number tile fills the row's height via `align-items: stretch` on the grid row. Do NOT lock it to `aspect-ratio: 1` — it should be a tall rectangle on rows with long descriptions.
8. **Icon tray hugs its content.** Use `width: fit-content` so the tray is only as wide as its icons, not stretched to fill the block.
9. **Examples tile is neutral with lighter shadow.** Right-side tile is always warm gray (`--ex-bg`), no highlight gradient overlay. Its shadow is lighter than the main blocks (`--shadow-light`) to create visual hierarchy.
10. **Descending numbering.** Number the top layer with the highest number and descend to 1 at the bottom, so the stack reads as "building up from the ground." The base plate anchors layer 1.
11. **Base plate has presence.** The base plate is 46px tall, fully rounded on all corners (`var(--r)`), carries centered uppercase text (e.g. a tagline), and has its own shadow. It is NOT a thin invisible strip.
12. **Color auto-ramp.** Layers auto-cycle through the 7-stop palette. If there are more layers than palette stops, the ramp wraps. Adjacent layers must be visually distinguishable.
13. **Grain texture is optional.** Include the SVG noise overlay on `.iso-ground` only if the design calls for a clay/stone feel. Default to a clean flat ground color.
14. **Icons can be emoji or SVG.** Both are valid. Emoji are simpler and match the toy aesthetic naturally. SVGs are preferred when the project has a curated icon set. Never mix the two in one stack.
15. **No comments in the code.**

---

## Token block

Emit exactly this shape in `:root`.

```css
:root {
  --font-stack: 'Poppins', 'Montserrat', system-ui, sans-serif;
  --r: 16px;
  --r-sm: 10px;
  --gap-row: 6px;
  --bg: #EDE7DD;
  --ground: #C9C2B4;
  --ink: #2B2620;
  --shadow-strong: rgba(30, 24, 14, 0.35);
  --shadow-light: rgba(30, 24, 14, 0.10);
  --highlight: linear-gradient(135deg, rgba(255,255,255,.22), rgba(255,255,255,0) 45%);
  --ex-bg: #DAD4C8;
  --ex-text: #4a4536;

  --c1: #6B3FA0;
  --c2: #2F5FA8;
  --c3: #1E8E8E;
  --c4: #3E9142;
  --c5: #D6A417;
  --c6: #D9722A;
  --c7: #B23A3A;
}
```

---

## Color palette — Rainbow ramp (default)

| Layer | Token | Hue | Hex |
|---|---|---|---|
| 1 (top) | `--c1` | Purple | `#6B3FA0` |
| 2 | `--c2` | Blue | `#2F5FA8` |
| 3 | `--c3` | Teal | `#1E8E8E` |
| 4 | `--c4` | Green | `#3E9142` |
| 5 | `--c5` | Gold | `#D6A417` |
| 6 | `--c6` | Orange | `#D9722A` |
| 7 (bottom) | `--c7` | Red | `#B23A3A` |

Icon tray darkening is handled by `rgba(0,0,0,.14)` overlay — no separate dark token needed per color. To swap the palette, replace the 7 `--cN` values in `:root`. Adjacent layers must differ in hue by at least 30°.

---

## Building a custom palette

If the user names a palette not listed:

1. Pick 5–9 hue stops evenly distributed around the wheel (or clustered for a warm/cool theme).
2. For each stop: pick a medium-saturation color at ~45–55% lightness that reads clearly as a solid block against a warm neutral ground.
3. Ensure WCAG AA contrast for white text on every base color (minimum 4.5:1).
4. Name the tokens `--c1` through `--cN` and document the mapping.
5. The icon tray darkening (`rgba(0,0,0,.14)`) works universally — no per-color dark variant needed.

---

## Scene markup

```html
<div class="iso-ground">

  <header class="stack-header">
    <div class="eyebrow">N layers</div>
    <h1>Stack Title</h1>
    <p>One-line description of what the stack represents.</p>
  </header>

  <div class="iso-stack">

    <div class="layer-row" style="--color:var(--c1)">
      <div class="num-tile">7</div>
      <div class="main-block">
        <p class="block-title">LAYER TITLE</p>
        <p class="block-desc">Short 2-3 line plain-English description of this layer.</p>
        <div class="icon-tray">
          <span>🔧</span><span>📦</span>
        </div>
      </div>
      <div class="ex-tile">
        <p class="ex-label">Examples:</p>
        <p class="ex-list">Term A, Term B, Term C</p>
      </div>
    </div>

    <!-- Repeat .layer-row for each layer, descending numbers -->

  </div>
  <div class="base-plate">TAGLINE TEXT HERE</div>

</div>
```

---

## Core CSS

```css
body {
  margin: 0;
  font-family: var(--font-stack);
  background: var(--bg);
  color: var(--ink);
  padding: 48px 20px 80px;
  -webkit-font-smoothing: antialiased;
}

.iso-ground {
  max-width: 920px;
  margin: 0 auto;
}

.stack-header {
  text-align: center;
  max-width: 640px;
  margin: 0 auto 56px;
}

.stack-header .eyebrow {
  font-size: 15px;
  letter-spacing: 0.02em;
  color: #8a7a5c;
  margin-bottom: 6px;
}

.stack-header h1 {
  font-size: clamp(32px, 5vw, 48px);
  margin: 0 0 14px;
  font-weight: 800;
}

.stack-header p {
  font-size: 17px;
  line-height: 1.5;
  color: #55503f;
}

.iso-stack {
  display: flex;
  flex-direction: column;
  gap: var(--gap-row);
}

.layer-row {
  display: grid;
  grid-template-columns: 90px 1fr 200px;
  gap: var(--gap-row);
  align-items: stretch;
}

.num-tile {
  background: var(--color);
  border-radius: var(--r);
  display: flex;
  align-items: center;
  justify-content: center;
  color: #fff;
  font-size: 42px;
  font-weight: 800;
  box-shadow: 2px 2px 0 var(--shadow-strong);
  background-image: var(--highlight);
}

.main-block {
  background: var(--color);
  border-radius: var(--r);
  padding: 18px 22px;
  box-shadow: 2px 2px 0 var(--shadow-strong);
  background-image: var(--highlight);
  display: flex;
  flex-direction: column;
  justify-content: center;
  gap: 10px;
}

.block-title {
  color: #fff;
  font-size: 22px;
  font-weight: 800;
  letter-spacing: 0.01em;
  margin: 0;
}

.block-desc {
  color: rgba(255,255,255,0.9);
  font-size: 14px;
  line-height: 1.4;
  margin: 0;
  max-width: 46ch;
}

.icon-tray {
  background: rgba(0,0,0,0.14);
  border-radius: var(--r-sm);
  padding: 8px 12px;
  display: flex;
  gap: 14px;
  align-items: center;
  width: fit-content;
}

.icon-tray span {
  font-size: 22px;
  filter: drop-shadow(1px 2px 0 rgba(0,0,0,0.25));
}

.icon-tray svg {
  width: 28px;
  height: 28px;
  fill: #fff;
  filter: drop-shadow(1px 2px 0 rgba(0,0,0,0.25));
}

.ex-tile {
  background: var(--ex-bg);
  border-radius: var(--r);
  padding: 16px 18px;
  box-shadow: 2px 2px 0 var(--shadow-light);
  display: flex;
  flex-direction: column;
  justify-content: center;
}

.ex-label {
  color: var(--color);
  font-weight: 800;
  font-size: 13px;
  margin: 0 0 6px;
}

.ex-list {
  font-size: 13px;
  color: var(--ex-text);
  line-height: 1.5;
  margin: 0;
}

.base-plate {
  max-width: 920px;
  margin: 10px auto 0;
  background: var(--ground);
  border-radius: 20px;
  height: 46px;
  box-shadow: 2px 2px 0 rgba(30, 24, 14, 0.15);
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 13px;
  letter-spacing: 0.08em;
  color: #6b6350;
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
    grid-template-columns: 60px 1fr;
    grid-template-areas:
      "num main"
      "ex ex";
  }
  .num-tile { grid-area: num; font-size: 28px; border-radius: 12px; }
  .main-block { grid-area: main; border-radius: 12px; padding: 14px 16px; }
  .ex-tile { grid-area: ex; border-radius: 12px; }
}

@media (max-width: 576px) {
  .layer-row {
    grid-template-columns: 1fr;
    grid-template-areas:
      "num"
      "main"
      "ex";
  }
  .num-tile {
    width: 52px;
    height: 52px;
    font-size: 24px;
    justify-self: start;
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

**Stack Header** — centered above the stack. Eyebrow count ("7 layers") in muted warm text, large bold title (`clamp(32px, 5vw, 48px)`), description paragraph in `#55503f`. Max-width 640px.

**Number Tile** — 90px wide (60px at ≤992px, 52px at ≤576px), stretches full row height via grid `align-items: stretch`. `--color` fill, white numeral at 42px (28px tablet, 24px mobile), `var(--highlight)` overlay, hard shadow `2px 2px 0 var(--shadow-strong)`. 800 weight. Never locked to `aspect-ratio: 1`.

**Main Block** — flex **column**: title → description → icon tray (stacked vertically, not side-by-side). `--color` fill with highlight gradient. Title is 22px bold white, description is 14px at 90% white opacity. Hard shadow `2px 2px 0 var(--shadow-strong)`. Padding 18px 22px.

**Icon Tray** — recessed shelf inside the main block, `rgba(0,0,0,.14)` flat fill (not a per-color dark token). `width: fit-content`. Contains 2–4 emoji spans or SVGs at 22–28px with `drop-shadow(1px 2px 0 rgba(0,0,0,.25))`. Radius `var(--r-sm)`. Wraps at ≤576px.

**Examples Tile** — warm neutral tile (`--ex-bg: #DAD4C8`), no highlight gradient. Lighter shadow `2px 2px 0 var(--shadow-light)`. Label in `--color` (bold 13px), example terms in `--ex-text` (13px regular). Spans full width below main block at ≤992px via `grid-area: ex`.

**Base Plate** — 46px tall, full `border-radius: 20px` on all corners, `--ground` fill, centered uppercase tagline text in `#6b6350` at 13px with `letter-spacing: 0.08em`. Shadow `2px 2px 0 rgba(30,24,14,.15)`. 10px margin above.

**Interactive press state** — On buttons or clickable blocks, reduce shadow to `1px 1px 0` and add `transform: translateY(1px)` on `:active`. Never use scale transforms.

---

## Data-driven rendering (JS component pattern)

```js
function renderLayerStack(layers) {
  const colors = ['c1','c2','c3','c4','c5','c6','c7'];
  const total = layers.length;
  return layers.map((layer, i) => {
    const c = colors[i % colors.length];
    const num = total - i;
    return `
      <div class="layer-row" style="--color:var(--${c})">
        <div class="num-tile">${num}</div>
        <div class="main-block">
          <p class="block-title">${layer.title}</p>
          <p class="block-desc">${layer.description}</p>
          <div class="icon-tray">${layer.icons.map(ic => '<span>' + ic + '</span>').join('')}</div>
        </div>
        <div class="ex-tile">
          <p class="ex-label">Examples:</p>
          <p class="ex-list">${layer.examples.join(', ')}</p>
        </div>
      </div>`;
  }).join('');
}
```

Data shape per layer:
```js
{ title: 'LAYER NAME', description: '...', icons: ['☕', '🔧'], examples: ['Term A', 'Term B'] }
```

Numbers are auto-assigned descending (total → 1) so the stack reads as building upward from the base plate.

---

## Quality floor

- Responsive to 360px with no horizontal scroll.
- `:focus-visible` outline in `--accent-light`, 3px, offset 3px.
- `@media (prefers-reduced-motion: reduce)` kills all transitions and smooth scroll.
- Body text ≥ 15px, line length under 60ch, white text contrast checked against every `--cN` base color (minimum WCAG AA 4.5:1).
- One HTML file unless the user asks otherwise. Fonts via one Google Fonts link or system stack.
- All blocks maintain equal row height within a single stack instance.
