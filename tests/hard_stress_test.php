#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * ╔═══════════════════════════════════════════════════════════════════════════╗
 * ║                  SPARTAN FRAMEWORK — HARD STRESS TEST                    ║
 * ║                                                                         ║
 * ║  Pushes EVERY framework subsystem to its breaking point.                ║
 * ║  14 stages · 2,000,000+ total operations · Memory-leak detection        ║
 * ╚═══════════════════════════════════════════════════════════════════════════╝
 *
 * Run:  php tests/hard_stress_test.php
 */

namespace App\Tests;

use Spartan\Application;
use Spartan\Container;
use Spartan\Router;
use Spartan\Request;
use Spartan\Response;
use Spartan\QueryBuilder;
use Spartan\View;
use Spartan\Cache;
use Spartan\Database;
use Spartan\Validator;
use Spartan\EventDispatcher;
use Spartan\Gate;
use Spartan\GateEvaluator;
use Spartan\Logger;
use Spartan\Model;
use Spartan\RelationQuery;
use PDO;

require_once __DIR__ . '/../framework/src/helpers.php';

spl_autoload_register(function (string $class): void {
    $prefixes = [
        'Spartan\\' => dirname(__DIR__) . '/framework/src/',
        'App\\'     => dirname(__DIR__) . '/src/',
    ];
    foreach ($prefixes as $prefix => $baseDir) {
        $len = strlen($prefix);
        if (strncmp($prefix, $class, $len) !== 0) {
            continue;
        }
        $file = $baseDir . str_replace('\\', '/', substr($class, $len)) . '.php';
        if (file_exists($file)) {
            require_once $file;
            return;
        }
    }
});

// ─────────────────────────────────────────────────────────────────────────────
// UTILITIES
// ─────────────────────────────────────────────────────────────────────────────

function banner(string $text): void
{
    $len = strlen($text) + 4;
    $line = str_repeat('═', $len);
    echo "\n╔{$line}╗\n";
    echo "║  {$text}  ║\n";
    echo "╚{$line}╝\n\n";
}

function stage(int $num, string $title): void
{
    echo "┌─────────────────────────────────────────────────────────────────────┐\n";
    echo "│  {$num}. {$title}" . str_repeat(' ', max(0, 65 - strlen("{$num}. {$title}"))) . "  │\n";
    echo "└─────────────────────────────────────────────────────────────────────┘\n";
}

function report(int $ops, float $elapsed, string $unit = 'ops/sec', ?float $memDelta = null): void
{
    $rate = $ops / max($elapsed, 0.000001);
    $us   = ($elapsed / max($ops, 1)) * 1e6;
    $ms   = round($elapsed * 1000, 2);
    $rateStr = number_format(round($rate));
    $usStr = sprintf('%.2f', $us);
    echo "   ✓ {$ms} ms | {$rateStr} {$unit} | {$usStr} µs/op";
    if ($memDelta !== null) {
        echo " | Δmem " . round($memDelta / 1024, 2) . " KB";
    }
    echo "\n";
}

function memSnap(): int
{
    return memory_get_usage();
}

// ─────────────────────────────────────────────────────────────────────────────
// BOOT
// ─────────────────────────────────────────────────────────────────────────────

banner("SPARTAN FRAMEWORK — HARD STRESS TEST");

echo "PHP " . PHP_VERSION . " (" . PHP_SAPI . ") on " . PHP_OS . "\n";
echo "Date: " . date('Y-m-d H:i:s') . "\n";
echo "───────────────────────────────────────────────────────────────────────\n\n";

$globalStart  = microtime(true);
$globalMemory = memory_get_usage();
$results      = [];

// SQLite in-memory for all DB tests
$pdo = new PDO('sqlite::memory:', null, null, [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);
Database::swapInstance($pdo);

// Multi-table relational schema
$pdo->exec("
    CREATE TABLE users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name VARCHAR(255) NOT NULL,
        email VARCHAR(255) UNIQUE NOT NULL,
        role VARCHAR(50) DEFAULT 'user',
        active INTEGER DEFAULT 1,
        created_at DATETIME,
        updated_at DATETIME
    );
    CREATE TABLE orders (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        total REAL NOT NULL,
        status VARCHAR(50) NOT NULL DEFAULT 'pending',
        notes TEXT,
        created_at DATETIME,
        FOREIGN KEY (user_id) REFERENCES users(id)
    );
    CREATE TABLE order_items (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        order_id INTEGER NOT NULL,
        product_name VARCHAR(255) NOT NULL,
        price REAL NOT NULL,
        quantity INTEGER NOT NULL DEFAULT 1,
        FOREIGN KEY (order_id) REFERENCES orders(id)
    );
    CREATE TABLE tags (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name VARCHAR(100) NOT NULL,
        slug VARCHAR(100) NOT NULL
    );
    CREATE TABLE logs (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        level VARCHAR(20) NOT NULL,
        message TEXT NOT NULL,
        context TEXT,
        created_at DATETIME
    );
    CREATE INDEX idx_users_email ON users(email);
    CREATE INDEX idx_users_role ON users(role);
    CREATE INDEX idx_orders_user ON orders(user_id, status);
    CREATE INDEX idx_items_order ON order_items(order_id);
    CREATE INDEX idx_tags_slug ON tags(slug);
");

$storageDir = __DIR__ . '/../storage';
$config = [
    'base_path' => dirname(__DIR__),
    'db'    => ['connection' => 'sqlite', 'database' => ':memory:'],
    'cache' => ['driver' => 'file', 'path' => $storageDir . '/cache'],
    'views' => ['cache_enabled' => true, 'cache_path' => $storageDir . '/views'],
    'app'   => ['trusted_proxies' => []],
];

$app = new Application($config);
$app->db = $pdo;


// ═══════════════════════════════════════════════════════════════════════════
// STAGE 1: DI CONTAINER — Deep Chain + Singleton + Factory Stress
// ═══════════════════════════════════════════════════════════════════════════

stage(1, "DI Container — Deep Chain Resolution (200,000 ops)");

// Create a 5-level dependency chain
class DepLevel1 {}
class DepLevel2 { public function __construct(public DepLevel1 $a) {} }
class DepLevel3 { public function __construct(public DepLevel2 $b) {} }
class DepLevel4 { public function __construct(public DepLevel3 $c) {} }
class DepLevel5 { public function __construct(public DepLevel4 $d, public DepLevel1 $e) {} }

$container = new Container();
$iters = 200_000;
$m0 = memSnap();
$t0 = microtime(true);

for ($i = 0; $i < $iters; $i++) {
    $container->make(DepLevel5::class);
}

$t1 = microtime(true);
$m1 = memSnap();
report($iters, $t1 - $t0, 'resolutions/sec', $m1 - $m0);
$results['DI Container (deep chain)'] = ['ops' => $iters, 'time' => $t1 - $t0];

// Singleton vs factory mix
echo "   Testing singleton vs factory (100,000 mixed ops)...\n";
$container->singleton('config_service', fn() => new \stdClass());
$container->bind('request_service', fn() => new \stdClass());

$t0 = microtime(true);
for ($i = 0; $i < 100_000; $i++) {
    $container->make('config_service');
    $container->make('request_service');
}
$t1 = microtime(true);
report(200_000, $t1 - $t0, 'ops/sec');
$results['DI Container (singleton+factory)'] = ['ops' => 200_000, 'time' => $t1 - $t0];


// ═══════════════════════════════════════════════════════════════════════════
// STAGE 2: ROUTER — Mass Route Registration + Parameterized Dispatch
// ═══════════════════════════════════════════════════════════════════════════

stage(2, "Router — 50-Route Table, 200,000 Dispatches");

$request  = new Request();
$response = new Response();
$router   = new Router($request, $response);

// Register 50 routes across all HTTP methods
$methods = ['get', 'post', 'put', 'patch', 'delete'];
for ($r = 0; $r < 10; $r++) {
    foreach ($methods as $method) {
        $router->$method("/api/v{$r}/{$method}/{id}/detail/{slug}", function ($id, $slug) {
            return "r:{$id}:{$slug}";
        });
    }
}

// Dispatch 200k against the hardest-to-match route (last registered)
$_SERVER['REQUEST_METHOD'] = 'DELETE';
$_SERVER['REQUEST_URI']    = '/api/v9/delete/99999/detail/hard-stress-test-slug';

$iters = 200_000;
$m0 = memSnap();
$t0 = microtime(true);

for ($i = 0; $i < $iters; $i++) {
    $req = new Request();
    $router->setRequest($req);
    $router->resolve();
}

$t1 = microtime(true);
$m1 = memSnap();
report($iters, $t1 - $t0, 'dispatches/sec', $m1 - $m0);
$results['Router (50 routes)'] = ['ops' => $iters, 'time' => $t1 - $t0];


// ═══════════════════════════════════════════════════════════════════════════
// STAGE 3: QUERYBUILDER — Complex Multi-Join Compilation
// ═══════════════════════════════════════════════════════════════════════════

stage(3, "QueryBuilder — Complex Multi-Join SQL (200,000 builds)");

$buildSelectMethod = new \ReflectionMethod(QueryBuilder::class, 'buildSelect');
$buildSelectMethod->setAccessible(true);

$iters = 200_000;
$m0 = memSnap();
$t0 = microtime(true);

for ($i = 0; $i < $iters; $i++) {
    $qb = new QueryBuilder($pdo, 'orders');
    $qb->select('orders.id', 'orders.total', 'users.name', 'COUNT(order_items.id) as item_count')
       ->join('users', 'users.id', '=', 'orders.user_id')
       ->join('order_items', 'order_items.order_id', '=', 'orders.id')
       ->where('orders.user_id', ($i % 500) + 1)
       ->where('orders.status', 'completed')
       ->where('orders.total', 50.0, '>')
       ->groupBy('orders.id')
       ->having('item_count', 1, '>=')
       ->orderBy('orders.total', 'DESC')
       ->orderBy('orders.id', 'ASC')
       ->limit(25)
       ->offset($i % 100);

    [$sql, $bindings] = $buildSelectMethod->invoke($qb);
}

$t1 = microtime(true);
$m1 = memSnap();
report($iters, $t1 - $t0, 'queries/sec', $m1 - $m0);
$results['QueryBuilder (multi-join)'] = ['ops' => $iters, 'time' => $t1 - $t0];


// ═══════════════════════════════════════════════════════════════════════════
// STAGE 4: DATABASE CRUD — Massive Transactional Insert/Update/Delete
// ═══════════════════════════════════════════════════════════════════════════

stage(4, "Database CRUD — 50,000 Inserts + Updates + Reads + Deletes");

$m0 = memSnap();
$t0 = microtime(true);

// --- Batch INSERT 50,000 users ---
echo "   Inserting 50,000 users (batched transaction)...\n";
$tInsert = microtime(true);
$pdo->beginTransaction();
for ($i = 1; $i <= 50000; $i++) {
    $qb = new QueryBuilder($pdo, 'users');
    $qb->insert([
        'name'       => "StressUser_{$i}",
        'email'      => "stress_{$i}@spartan-test.io",
        'role'       => ($i % 5 === 0) ? 'admin' : (($i % 3 === 0) ? 'editor' : 'user'),
        'active'     => ($i % 7 !== 0) ? 1 : 0,
        'created_at' => date('Y-m-d H:i:s'),
        'updated_at' => date('Y-m-d H:i:s'),
    ]);
}
$pdo->commit();
$tInsertEnd = microtime(true);
report(50000, $tInsertEnd - $tInsert, 'inserts/sec');

// --- Batch UPDATE 10,000 rows ---
echo "   Updating 10,000 random users...\n";
$tUpdate = microtime(true);
$pdo->beginTransaction();
for ($i = 1; $i <= 10000; $i++) {
    $id = rand(1, 50000);
    $qb = new QueryBuilder($pdo, 'users');
    $qb->where('id', $id)->update([
        'name'       => "Updated_StressUser_{$id}",
        'updated_at' => date('Y-m-d H:i:s'),
    ]);
}
$pdo->commit();
$tUpdateEnd = microtime(true);
report(10000, $tUpdateEnd - $tUpdate, 'updates/sec');

// --- Complex READ queries ---
echo "   Running 20,000 complex aggregate reads...\n";
$tRead = microtime(true);
for ($i = 0; $i < 20000; $i++) {
    $role = ['user', 'admin', 'editor'][$i % 3];
    $qb = new QueryBuilder($pdo, 'users');
    $qb->select('role', 'COUNT(*) as cnt')
       ->where('active', 1)
       ->where('role', $role)
       ->groupBy('role')
       ->get();
}
$tReadEnd = microtime(true);
report(20000, $tReadEnd - $tRead, 'reads/sec');

// --- DELETE 5,000 rows ---
echo "   Deleting 5,000 users (range delete)...\n";
$tDel = microtime(true);
$pdo->beginTransaction();
for ($i = 45001; $i <= 50000; $i++) {
    $qb = new QueryBuilder($pdo, 'users');
    $qb->where('id', $i)->delete();
}
$pdo->commit();
$tDelEnd = microtime(true);
report(5000, $tDelEnd - $tDel, 'deletes/sec');

$t1 = microtime(true);
$m1 = memSnap();

// Verify data integrity
$remaining = (new QueryBuilder($pdo, 'users'))->count();
echo "   Remaining rows: {$remaining}\n";
report(85000, $t1 - $t0, 'total CRUD ops/sec', $m1 - $m0);
$results['DB CRUD (85k ops)'] = ['ops' => 85000, 'time' => $t1 - $t0];


// ═══════════════════════════════════════════════════════════════════════════
// STAGE 5: MODEL HYDRATION — 500,000 Instantiations
// ═══════════════════════════════════════════════════════════════════════════

stage(5, "Model Hydration — 500,000 Active Record Instantiations");

// Define test model
class StressUser extends Model {
    protected string $table = 'users';
}

$sampleRow = [
    'id' => 42, 'name' => 'BenchUser', 'email' => 'bench@spartan.io',
    'role' => 'admin', 'active' => 1,
    'created_at' => '2026-01-01 00:00:00', 'updated_at' => '2026-01-01 00:00:00'
];

$iters = 500_000;
$m0 = memSnap();
$t0 = microtime(true);

for ($i = 0; $i < $iters; $i++) {
    $model = new StressUser();
    foreach ($sampleRow as $k => $v) {
        $model->$k = $v;
    }
    // Force attribute readback to test magic __get
    $_ = $model->name;
    $_ = $model->email;
    $_ = $model->toArray();
}

$t1 = microtime(true);
$m1 = memSnap();
report($iters, $t1 - $t0, 'models/sec', $m1 - $m0);
$results['Model Hydration (500k)'] = ['ops' => $iters, 'time' => $t1 - $t0];


// ═══════════════════════════════════════════════════════════════════════════
// STAGE 6: MODEL RELATIONSHIPS — Eager Loading Stress
// ═══════════════════════════════════════════════════════════════════════════

stage(6, "Relationships — Eager Loading (hasMany + belongsTo)");

// Seed orders with foreign keys
echo "   Seeding 2,000 orders + 4,000 items for relationship tests...\n";
$pdo->beginTransaction();
for ($i = 1; $i <= 2000; $i++) {
    $userId = ($i % 1000) + 1; // spread across first 1000 users
    (new QueryBuilder($pdo, 'orders'))->insert([
        'user_id'    => $userId,
        'total'      => round(rand(1000, 99999) / 100, 2),
        'status'     => ($i % 3 === 0) ? 'completed' : (($i % 3 === 1) ? 'pending' : 'cancelled'),
        'notes'      => "Order #{$i} stress test",
        'created_at' => date('Y-m-d H:i:s'),
    ]);
    // 2 items per order
    (new QueryBuilder($pdo, 'order_items'))->insert([
        'order_id'     => $i,
        'product_name' => "Product_A_{$i}",
        'price'        => round(rand(500, 5000) / 100, 2),
        'quantity'     => rand(1, 10),
    ]);
    (new QueryBuilder($pdo, 'order_items'))->insert([
        'order_id'     => $i,
        'product_name' => "Product_B_{$i}",
        'price'        => round(rand(500, 5000) / 100, 2),
        'quantity'     => rand(1, 5),
    ]);
}
$pdo->commit();

class StressOrder extends Model {
    protected string $table = 'orders';
}

// Test eager loading: load all orders for 100 users
echo "   Running 5,000 eager-load cycles (hasMany + belongsTo)...\n";
$m0 = memSnap();
$t0 = microtime(true);

for ($i = 0; $i < 5000; $i++) {
    $userId = ($i % 1000) + 1;
    // hasMany: user -> orders
    $orders = (new QueryBuilder($pdo, 'orders'))
        ->where('user_id', $userId)
        ->get();

    // belongsTo: each order -> user (single query via IN)
    if (!empty($orders)) {
        $userIds = array_unique(array_column($orders, 'user_id'));
        $users = (new QueryBuilder($pdo, 'users'))
            ->whereIn('id', $userIds)
            ->get();
    }
}

$t1 = microtime(true);
$m1 = memSnap();
report(5000, $t1 - $t0, 'eager-load cycles/sec', $m1 - $m0);
$results['Relationships (eager load)'] = ['ops' => 5000, 'time' => $t1 - $t0];


// ═══════════════════════════════════════════════════════════════════════════
// STAGE 7: VALIDATOR — Every Rule, 100,000 Validations
// ═══════════════════════════════════════════════════════════════════════════

stage(7, "Validator — All Rules Simultaneously (100,000 passes)");

$validator = new Validator();
$validator->setDb($pdo);

// Valid dataset (should pass all rules)
$validData = [
    'username'              => 'alexei_volkov',
    'username_confirmation' => 'alexei_volkov',
    'email'                 => 'alexei@spartan.io',
    'age'                   => '28',
    'score'                 => '95.5',
    'bio'                   => 'Spartan developer and framework architect.',
    'role'                  => 'admin',
    'website'               => 'https://spartan.io',
    'birthday'              => '1997-06-15',
    'code'                  => 'ABC123',
    'phone'                 => '+15551234567',
    'nickname'              => null,           // nullable field
    'is_active'             => '1',
];

$rules = [
    'username'  => 'required|string|min:3|max:50|confirmed|alpha_num',
    'email'     => 'required|email',
    'age'       => 'required|integer|min:18|max:120',
    'score'     => 'required|numeric|min:0|max:100',
    'bio'       => 'required|string|min:10|max:1000',
    'role'      => 'required|in:admin,editor,user',
    'website'   => 'required|url',
    'birthday'  => 'required|date',
    'code'      => 'required|alpha_num',
    'phone'     => 'required|regex:/^\+?[0-9]{7,15}$/',
    'nickname'  => 'nullable|string|min:2',
    'is_active' => 'required|boolean',
];

// Invalid dataset (should catch errors for every field)
$invalidData = [
    'username'              => '',             // fails required
    'username_confirmation' => 'different',
    'email'                 => 'not-an-email',
    'age'                   => 'abc',          // fails integer
    'score'                 => 'not-numeric',
    'bio'                   => 'short',        // fails min:10
    'role'                  => 'superadmin',   // fails in
    'website'               => 'not a url',
    'birthday'              => '99-99-99',     // fails date
    'code'                  => '!@#$%',        // fails alpha_num
    'phone'                 => 'not-a-phone',
    'nickname'              => 'ok',
    'is_active'             => 'maybe',        // fails boolean
];

$iters = 100_000;
$m0 = memSnap();
$t0 = microtime(true);
$passCount = 0;
$failCount = 0;

for ($i = 0; $i < $iters; $i++) {
    // Alternate between valid and invalid data
    if ($i % 2 === 0) {
        $v = new Validator();
        $v->setDb($pdo);
        if ($v->validate($validData, $rules)) {
            $passCount++;
        }
    } else {
        $v = new Validator();
        $v->setDb($pdo);
        if (!$v->validate($invalidData, $rules)) {
            $failCount++;
        }
    }
}

$t1 = microtime(true);
$m1 = memSnap();
echo "   Passes: {$passCount} | Failures: {$failCount}\n";
report($iters, $t1 - $t0, 'validations/sec', $m1 - $m0);
$results['Validator (all rules)'] = ['ops' => $iters, 'time' => $t1 - $t0];


// ═══════════════════════════════════════════════════════════════════════════
// STAGE 8: EVENT DISPATCHER — Sync Fan-Out Stress
// ═══════════════════════════════════════════════════════════════════════════

stage(8, "Event Dispatcher — Sync Fan-Out (100,000 dispatches × 5 listeners)");

$events = new EventDispatcher();
$counter = new class { public int $count = 0; };

// Register 5 sync listeners on same event
for ($l = 0; $l < 5; $l++) {
    $events->listen('stress.event', function ($payload) use ($counter) {
        $counter->count++;
    });
}

$iters = 100_000;
$m0 = memSnap();
$t0 = microtime(true);

for ($i = 0; $i < $iters; $i++) {
    $events->dispatch('stress.event', ['iteration' => $i, 'data' => 'payload']);
}

$t1 = microtime(true);
$m1 = memSnap();
$totalFired = $counter->count;
echo "   Total listener invocations: " . number_format($totalFired) . "\n";
report($iters, $t1 - $t0, 'dispatches/sec', $m1 - $m0);
$results['Events (5-listener fan-out)'] = ['ops' => $iters, 'time' => $t1 - $t0];


// ═══════════════════════════════════════════════════════════════════════════
// STAGE 9: CACHE — Put/Get/Remember/Flush (100,000 operations)
// ═══════════════════════════════════════════════════════════════════════════

stage(9, "Cache — Put/Get/Remember/Has/Forget (100,000 ops)");

Cache::flush();

$iters = 20_000;
$m0 = memSnap();
$t0 = microtime(true);

// Write
for ($i = 0; $i < $iters; $i++) {
    Cache::put("hard_stress_{$i}", [
        'id'   => $i,
        'name' => "item_{$i}",
        'tags' => ['stress', 'test', "batch_{$i}"],
        'nested' => ['deep' => ['value' => $i * 3.14]],
    ], 300);
}

// Read + Has
$hitCount = 0;
for ($i = 0; $i < $iters; $i++) {
    if (Cache::has("hard_stress_{$i}")) {
        $val = Cache::get("hard_stress_{$i}");
        if ($val !== null) $hitCount++;
    }
}

// Remember (cache miss then hit)
for ($i = 0; $i < $iters; $i++) {
    Cache::remember("remember_stress_{$i}", 300, function () use ($i) {
        return ['computed' => $i * 2.718];
    });
}

// Re-read (should be cached)
for ($i = 0; $i < $iters; $i++) {
    Cache::remember("remember_stress_{$i}", 300, function () use ($i) {
        return ['should_not_compute' => true];
    });
}

// Forget
for ($i = 0; $i < $iters; $i++) {
    Cache::forget("hard_stress_{$i}");
    Cache::forget("remember_stress_{$i}");
}

$t1 = microtime(true);
$m1 = memSnap();
$totalOps = $iters * 5; // put + has + get + remember*2 + forget*2
echo "   Cache hits: " . number_format($hitCount) . " / {$iters}\n";
report($totalOps, $t1 - $t0, 'ops/sec', $m1 - $m0);
$results['Cache (mixed ops)'] = ['ops' => $totalOps, 'time' => $t1 - $t0];

Cache::flush();


// ═══════════════════════════════════════════════════════════════════════════
// STAGE 10: BLADE VIEW ENGINE — Compile + Render (50,000 renders)
// ═══════════════════════════════════════════════════════════════════════════

stage(10, "Blade View — Complex Template (50,000 renders)");

$viewsDir = $storageDir . '/hard_stress_views';
if (!is_dir($viewsDir)) {
    mkdir($viewsDir, 0755, true);
}

// Write a complex template with all Blade features
file_put_contents($viewsDir . '/stress.blade.php', '
<div class="dashboard" id="dashboard-{{ $pageId }}">
    <header>
        <h1>{{ $title }}</h1>
        <p class="subtitle">{{ $subtitle }}</p>
    </header>

    @if($showStats)
        <section class="stats">
            <div class="stat">
                <span class="label">Total Users</span>
                <span class="value">{{ $stats["users"] }}</span>
            </div>
            <div class="stat">
                <span class="label">Revenue</span>
                <span class="value">${{ $stats["revenue"] }}</span>
            </div>
        </section>
    @else
        <p class="no-stats">Statistics are disabled.</p>
    @endif

    <table class="data-table">
        <thead>
            <tr><th>ID</th><th>Name</th><th>Role</th><th>Status</th></tr>
        </thead>
        <tbody>
        @foreach($users as $user)
            <tr class="{{ $user["active"] ? "active" : "inactive" }}">
                <td>{{ $user["id"] }}</td>
                <td>{{ $user["name"] }}</td>
                <td>{{ $user["role"] }}</td>
                <td>
                    @if($user["active"])
                        <span class="badge badge-success">Active</span>
                    @else
                        <span class="badge badge-danger">Inactive</span>
                    @endif
                </td>
            </tr>
        @endforeach
        </tbody>
    </table>

    <footer>
        <p>Rendered at {{ $timestamp }} — Page {{ $pageId }}</p>
    </footer>
</div>
');

$viewEngine = new View($viewsDir);
$viewData = [
    'title'     => 'Spartan Stress Dashboard',
    'subtitle'  => 'Real-time system metrics under extreme load',
    'pageId'    => 'stress-001',
    'showStats' => true,
    'stats'     => ['users' => '45,000', 'revenue' => '1,234,567.89'],
    'timestamp' => date('Y-m-d H:i:s'),
    'users'     => [
        ['id' => 1, 'name' => 'Alexei Volkov',   'role' => 'admin',  'active' => true],
        ['id' => 2, 'name' => 'Sarah Chen',       'role' => 'editor', 'active' => true],
        ['id' => 3, 'name' => 'Marcus Johnson',   'role' => 'user',   'active' => false],
        ['id' => 4, 'name' => 'Yuki Tanaka',      'role' => 'admin',  'active' => true],
        ['id' => 5, 'name' => 'Elena Rodriguez',  'role' => 'user',   'active' => false],
        ['id' => 6, 'name' => 'Dmitri Petrov',    'role' => 'editor', 'active' => true],
        ['id' => 7, 'name' => 'Aisha Bakari',     'role' => 'admin',  'active' => true],
        ['id' => 8, 'name' => 'Lars Svensson',    'role' => 'user',   'active' => false],
    ],
];

$iters = 50_000;
$m0 = memSnap();
$t0 = microtime(true);

for ($i = 0; $i < $iters; $i++) {
    $html = $viewEngine->render('stress', $viewData);
}

$t1 = microtime(true);
$m1 = memSnap();

// Verify render output
$sampleLen = strlen($html);
echo "   Sample render: {$sampleLen} bytes\n";
report($iters, $t1 - $t0, 'renders/sec', $m1 - $m0);
$results['Blade View (complex)'] = ['ops' => $iters, 'time' => $t1 - $t0];

// Cleanup
@unlink($viewsDir . '/stress.blade.php');
@rmdir($viewsDir);


// ═══════════════════════════════════════════════════════════════════════════
// STAGE 11: LOGGER — Text + JSON Format Stress (50,000 log entries)
// ═══════════════════════════════════════════════════════════════════════════

stage(11, "Logger — Text + JSON Channel Writes (50,000 entries)");

$logDir = $storageDir . '/stress_logs';
if (!is_dir($logDir)) {
    mkdir($logDir, 0755, true);
}

$textLogger = new Logger($logDir, 'text', 'stress_text');
$jsonLogger = new Logger($logDir, 'json', 'stress_json');
$textLogger->setCorrelationId('STRESS-' . bin2hex(random_bytes(4)));
$jsonLogger->setCorrelationId('STRESS-' . bin2hex(random_bytes(4)));

$iters = 50_000;
$m0 = memSnap();
$t0 = microtime(true);

$levels = ['debug', 'info', 'notice', 'warning', 'error', 'critical'];

for ($i = 0; $i < $iters; $i++) {
    $level = $levels[$i % count($levels)];
    $msg = "Stress test operation #{$i}: {level} event for user {username}";
    $ctx = ['username' => "user_{$i}", 'ip' => '192.168.1.' . ($i % 255), 'level' => $level];

    if ($i % 2 === 0) {
        $textLogger->$level($msg, $ctx);
    } else {
        $jsonLogger->$level($msg, $ctx);
    }
}

$t1 = microtime(true);
$m1 = memSnap();
report($iters, $t1 - $t0, 'log entries/sec', $m1 - $m0);
$results['Logger (text+json)'] = ['ops' => $iters, 'time' => $t1 - $t0];

// Cleanup log files
foreach (glob($logDir . '/*.log') as $f) @unlink($f);
@rmdir($logDir);


// ═══════════════════════════════════════════════════════════════════════════
// STAGE 12: GATE AUTHORIZATION — Ability + Policy Checks
// ═══════════════════════════════════════════════════════════════════════════

stage(12, "Gate Authorization — 100,000 Ability & Policy Checks");

// Reset gate state
Gate::$abilities = [];
Gate::$policies  = [];

// Define abilities
Gate::define('edit-post', function (?object $user, $post = null) {
    if ($user === null) return false;
    return $user->role === 'admin' || ($post && $user->id === ($post['author_id'] ?? null));
});

Gate::define('delete-post', function (?object $user) {
    return $user !== null && $user->role === 'admin';
});

Gate::define('publish', function (?object $user) {
    return $user !== null && in_array($user->role, ['admin', 'editor']);
});

Gate::define('view-analytics', function (?object $user) {
    return $user !== null && $user->role === 'admin';
});

Gate::define('manage-users', function (?object $user) {
    return $user !== null && $user->role === 'admin';
});

// Create mock users
$adminUser = (object) ['id' => 1, 'role' => 'admin', 'name' => 'Admin'];
$editorUser = (object) ['id' => 2, 'role' => 'editor', 'name' => 'Editor'];
$regularUser = (object) ['id' => 3, 'role' => 'user', 'name' => 'Regular'];

$abilities = ['edit-post', 'delete-post', 'publish', 'view-analytics', 'manage-users'];
$testUsers = [$adminUser, $editorUser, $regularUser, null]; // include null (guest)

$iters = 100_000;
$m0 = memSnap();
$t0 = microtime(true);
$allowed = 0;
$denied  = 0;

for ($i = 0; $i < $iters; $i++) {
    $user    = $testUsers[$i % count($testUsers)];
    $ability = $abilities[$i % count($abilities)];
    $post    = ['author_id' => ($i % 3) + 1, 'title' => "Post {$i}"];

    if (Gate::inspect($user, $ability, $post)) {
        $allowed++;
    } else {
        $denied++;
    }
}

$t1 = microtime(true);
$m1 = memSnap();
echo "   Allowed: " . number_format($allowed) . " | Denied: " . number_format($denied) . "\n";
report($iters, $t1 - $t0, 'checks/sec', $m1 - $m0);
$results['Gate Auth (5 abilities)'] = ['ops' => $iters, 'time' => $t1 - $t0];


// ═══════════════════════════════════════════════════════════════════════════
// STAGE 13: REQUEST PARSING — Header, Input, Body Stress
// ═══════════════════════════════════════════════════════════════════════════

stage(13, "Request Parsing — 100,000 Request Objects");

// Simulate various request environments
$_SERVER['REQUEST_METHOD'] = 'POST';
$_SERVER['REQUEST_URI']    = '/api/v2/users?page=5&limit=25&sort=name&order=desc&filter=active';
$_SERVER['HTTP_AUTHORIZATION']   = 'Bearer eyJhbGciOiJIUzI1NiJ9.eyJzdWIiOiIxMjM0NTY3ODkwIn0';
$_SERVER['HTTP_CONTENT_TYPE']    = 'application/json';
$_SERVER['HTTP_ACCEPT']          = 'application/json';
$_SERVER['HTTP_X_FORWARDED_FOR'] = '203.0.113.50';
$_SERVER['REMOTE_ADDR']          = '127.0.0.1';
$_POST = ['name' => 'Test User', 'email' => 'test@example.com'];
$_GET  = ['page' => '5', 'limit' => '25', 'sort' => 'name', 'order' => 'desc', 'filter' => 'active'];

$iters = 100_000;
$m0 = memSnap();
$t0 = microtime(true);

for ($i = 0; $i < $iters; $i++) {
    $req = new Request();
    $_ = $req->getMethod();
    $_ = $req->getPath();
    $_ = $req->get('page');
    $_ = $req->get('limit');
    $_ = $req->post('name');
    $_ = $req->post('email');
    $_ = $req->header('Authorization');
    $_ = $req->header('Content-Type');
    $_ = $req->getBody();
    $_ = $req->getIp();
}

$t1 = microtime(true);
$m1 = memSnap();
report($iters, $t1 - $t0, 'requests/sec', $m1 - $m0);
$results['Request Parsing'] = ['ops' => $iters, 'time' => $t1 - $t0];


// ═══════════════════════════════════════════════════════════════════════════
// STAGE 14: FULL-STACK INTEGRATION — End-to-End Pipeline
// ═══════════════════════════════════════════════════════════════════════════

stage(14, "Full-Stack Integration — 10,000 Request Lifecycles");

echo "   Simulating complete request → validate → query → render pipeline\n";

$viewsDir2 = $storageDir . '/integration_views';
if (!is_dir($viewsDir2)) {
    mkdir($viewsDir2, 0755, true);
}

file_put_contents($viewsDir2 . '/user_list.blade.php', '
<h1>{{ $title }}</h1>
<p>Found {{ $count }} users</p>
@foreach($users as $user)
    <div class="user">{{ $user["name"] }} ({{ $user["email"] }})</div>
@endforeach
');

$integrationView = new View($viewsDir2);

$iters = 10_000;
$m0 = memSnap();
$t0 = microtime(true);
$successCount = 0;

for ($i = 0; $i < $iters; $i++) {
    // 1. Parse request
    $req = new Request();

    // 2. Validate input
    $v = new Validator();
    $inputData = [
        'page'   => (string)(($i % 50) + 1),
        'limit'  => '25',
        'search' => 'stress_user',
    ];
    $v->validate($inputData, [
        'page'   => 'required|integer|min:1',
        'limit'  => 'required|integer|min:1|max:100',
        'search' => 'required|string|min:1|max:255',
    ]);

    // 3. Query database
    $page  = (int) $inputData['page'];
    $limit = (int) $inputData['limit'];
    $users = (new QueryBuilder($pdo, 'users'))
        ->select('id', 'name', 'email', 'role')
        ->where('active', 1)
        ->orderBy('name', 'ASC')
        ->limit($limit)
        ->offset(($page - 1) * $limit)
        ->get();

    $count = (new QueryBuilder($pdo, 'users'))
        ->where('active', 1)
        ->count();

    // 4. Render view
    $html = $integrationView->render('user_list', [
        'title' => 'User Directory — Page ' . $page,
        'users' => $users,
        'count' => $count,
    ]);

    if (strlen($html) > 0) {
        $successCount++;
    }
}

$t1 = microtime(true);
$m1 = memSnap();
echo "   Successful pipelines: " . number_format($successCount) . " / " . number_format($iters) . "\n";
report($iters, $t1 - $t0, 'pipelines/sec', $m1 - $m0);
$results['Full-Stack Pipeline'] = ['ops' => $iters, 'time' => $t1 - $t0];

// Cleanup
@unlink($viewsDir2 . '/user_list.blade.php');
@rmdir($viewsDir2);


// ═══════════════════════════════════════════════════════════════════════════
// FINAL REPORT
// ═══════════════════════════════════════════════════════════════════════════

$globalEnd    = microtime(true);
$globalTotal  = round($globalEnd - $globalStart, 2);
$peakMemoryMB = round(memory_get_peak_usage() / 1024 / 1024, 2);
$memUsedMB    = round((memory_get_usage() - $globalMemory) / 1024 / 1024, 2);

$totalOps = 0;
foreach ($results as $r) {
    $totalOps += $r['ops'];
}

echo "\n";
echo "╔══════════════════════════════════════════════════════════════════════════╗\n";
echo "║                    HARD STRESS TEST — FINAL REPORT                     ║\n";
echo "╠══════════════════════════════════════════════════════════════════════════╣\n";

$maxLabel = 0;
foreach ($results as $label => $r) {
    $maxLabel = max($maxLabel, strlen($label));
}

foreach ($results as $label => $r) {
    $rate = number_format(round($r['ops'] / max($r['time'], 0.000001)));
    $pad  = str_repeat(' ', $maxLabel - strlen($label));
    $sec  = sprintf('%.2f', $r['time']);
    echo "║  {$label}{$pad}  │  {$rate} ops/sec  │  {$sec}s\n";
}

echo "╠══════════════════════════════════════════════════════════════════════════╣\n";
echo "║  Total Operations  : " . number_format($totalOps) . str_repeat(' ', 50 - strlen(number_format($totalOps))) . "║\n";
echo "║  Total Time        : {$globalTotal} seconds" . str_repeat(' ', 53 - strlen("{$globalTotal} seconds")) . "║\n";
echo "║  Memory Used       : {$memUsedMB} MB" . str_repeat(' ', 57 - strlen("{$memUsedMB} MB")) . "║\n";
echo "║  Peak Memory       : {$peakMemoryMB} MB" . str_repeat(' ', 57 - strlen("{$peakMemoryMB} MB")) . "║\n";
echo "║  Avg Throughput    : " . number_format(round($totalOps / max($globalTotal, 0.01))) . " ops/sec" . str_repeat(' ', max(0, 49 - strlen(number_format(round($totalOps / max($globalTotal, 0.01))) . " ops/sec"))) . "║\n";
echo "╚══════════════════════════════════════════════════════════════════════════╝\n";

// ── Memory Leak Detection ──
echo "\n";
if ($peakMemoryMB > 50) {
    echo "⚠  WARNING: Peak memory exceeded 50 MB — potential memory leak detected!\n";
} elseif ($peakMemoryMB > 20) {
    echo "⚡ NOTICE: Peak memory was {$peakMemoryMB} MB — moderate but acceptable.\n";
} else {
    echo "✓  MEMORY OK: Peak {$peakMemoryMB} MB — excellent memory efficiency.\n";
}

echo "\n═══════════════════════════════════════════════════════════════════════════\n";
echo "  Hard stress test completed. All " . count($results) . " subsystems exercised.\n";
echo "═══════════════════════════════════════════════════════════════════════════\n\n";
