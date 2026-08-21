<?php

declare(strict_types=1);

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

echo "===================================================================\n";
echo "           SPARTAN FRAMEWORK STRESS TEST & BENCHMARK              \n";
echo "===================================================================\n\n";

$startTime = microtime(true);
$startMemory = memory_get_usage();

// Setup in-memory sqlite
$pdo = new PDO('sqlite::memory:', null, null, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);
Database::swapInstance($pdo);

$pdo->exec("CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, name VARCHAR(255), email VARCHAR(255), active INTEGER, created_at DATETIME)");

$config = [
    'db' => ['connection' => 'sqlite', 'database' => ':memory:'],
    'cache' => ['driver' => 'file', 'path' => __DIR__ . '/../storage/cache'],
    'views' => ['cache_enabled' => true, 'cache_path' => __DIR__ . '/../storage/views'],
];

$app = new Application($config);

// -----------------------------------------------------------------------------
// 1. DI CONTAINER STRESS TEST (100,000 Resolutions)
// -----------------------------------------------------------------------------
echo "1. Testing DI Container Auto-Resolution (100,000 iterations)... ";
$t0 = microtime(true);

class StressTestDependency {}
class StressTestService {
    public function __construct(public StressTestDependency $dep) {}
}

$container = new Container();
for ($i = 0; $i < 100000; $i++) {
    $instance = $container->make(StressTestService::class);
}
$t1 = microtime(true);
$duration = round(($t1 - $t0) * 1000, 2);
$rps = number_format(round(100000 / ($t1 - $t0)));
echo "DONE ({$duration} ms | {$rps} ops/sec)\n";

// -----------------------------------------------------------------------------
// 2. ROUTER MATCHING STRESS TEST (100,000 Route Dispatches)
// -----------------------------------------------------------------------------
echo "2. Testing Router Matching & Param Extraction (100,000 iterations)... ";
$t0 = microtime(true);

$request = new Request();
$response = new Response();
$router = new Router($request, $response);

$router->get('/users/{id}/posts/{slug}', function ($id, $slug) {
    return "User: {$id}, Post: {$slug}";
});

// Mock environment for request
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = '/users/42/posts/zero-dependency-php-framework';

for ($i = 0; $i < 100000; $i++) {
    $req = new Request();
    $router->setRequest($req);
    $router->resolve();
}
$t1 = microtime(true);
$duration = round(($t1 - $t0) * 1000, 2);
$rps = number_format(round(100000 / ($t1 - $t0)));
echo "DONE ({$duration} ms | {$rps} req/sec)\n";

// -----------------------------------------------------------------------------
// 3. QUERYBUILDER SQL GENERATION STRESS TEST (50,000 Builds)
// -----------------------------------------------------------------------------
echo "3. Testing QueryBuilder SQL Generation & Binding (50,000 iterations)... ";
$t0 = microtime(true);

for ($i = 0; $i < 50000; $i++) {
    $qb = new QueryBuilder($pdo, 'users');
    $qb->select('id', 'name', 'email')
       ->join('orders', 'orders.user_id', '=', 'users.id')
       ->where('active', 1)
       ->where('email', '%@example.com', 'LIKE')
       ->groupBy('users.id')
       ->having('total', 100, '>')
       ->orderBy('created_at', 'DESC')
       ->limit(20)
       ->offset(40);
}
$t1 = microtime(true);
$duration = round(($t1 - $t0) * 1000, 2);
$rps = number_format(round(50000 / ($t1 - $t0)));
echo "DONE ({$duration} ms | {$rps} queries/sec)\n";

// -----------------------------------------------------------------------------
// 4. DATABASE IN-MEMORY CRUD STRESS TEST (10,000 Writes & Reads)
// -----------------------------------------------------------------------------
echo "4. Testing Database In-Memory SQLite Writes & Reads (10,000 rows)... ";
$t0 = microtime(true);

$pdo->beginTransaction();
for ($i = 1; $i <= 10000; $i++) {
    $qb = new QueryBuilder($pdo, 'users');
    $qb->insert([
        'name' => "User {$i}",
        'email' => "user{$i}@spartan.org",
        'active' => $i % 2,
        'created_at' => date('Y-m-d H:i:s'),
    ]);
}
$pdo->commit();

$count = (new QueryBuilder($pdo, 'users'))->where('active', 1)->count();

$t1 = microtime(true);
$duration = round(($t1 - $t0) * 1000, 2);
$rps = number_format(round(10000 / ($t1 - $t0)));
echo "DONE ({$duration} ms | {$rps} inserts/sec | Active count: {$count})\n";

// -----------------------------------------------------------------------------
// 5. CACHE IN-MEMORY STRESS TEST (50,000 Put/Get Ops)
// -----------------------------------------------------------------------------
echo "5. Testing File Cache Read/Write Operations (50,000 operations)... ";
$t0 = microtime(true);

for ($i = 0; $i < 25000; $i++) {
    Cache::put("key_{$i}", ["id" => $i, "data" => "spartan_cache_test"], 60);
    $val = Cache::get("key_{$i}");
}
$t1 = microtime(true);
$duration = round(($t1 - $t0) * 1000, 2);
$rps = number_format(round(50000 / ($t1 - $t0)));
echo "DONE ({$duration} ms | {$rps} ops/sec)\n";

Cache::flush();

// -----------------------------------------------------------------------------
// 6. BLADE COMPILER & RENDERER STRESS TEST (10,000 Renders)
// -----------------------------------------------------------------------------
echo "6. Testing Blade View Compilation & Rendering (10,000 renders)... ";
$t0 = microtime(true);

$viewsDir = __DIR__ . '/../storage/stress_views';
if (!is_dir($viewsDir)) {
    mkdir($viewsDir, 0755, true);
}
file_put_contents($viewsDir . '/benchmark.blade.php', '
<div class="user-card">
    <h1>{{ $user["name"] }}</h1>
    <p>Email: {{ $user["email"] }}</p>
    @if($user["active"])
        <span class="status active">Active Member</span>
    @else
        <span class="status inactive">Inactive</span>
    @endif
    <ul>
    @foreach($items as $item)
        <li>{{ $item }}</li>
    @endforeach
    </ul>
</div>
');

$viewEngine = new View($viewsDir);
$params = [
    'user' => ['name' => 'Alexei Volkov', 'email' => 'alexei@spartan.org', 'active' => true],
    'items' => ['DI Container', 'QueryBuilder', 'Blade Engine', 'Event Queue', 'RBAC Security']
];

for ($i = 0; $i < 10000; $i++) {
    $html = $viewEngine->render('benchmark', $params);
}

@unlink($viewsDir . '/benchmark.blade.php');
@rmdir($viewsDir);

$t1 = microtime(true);
$duration = round(($t1 - $t0) * 1000, 2);
$rps = number_format(round(10000 / ($t1 - $t0)));
echo "DONE ({$duration} ms | {$rps} renders/sec)\n";

// -----------------------------------------------------------------------------
// SUMMARY & RESOURCE CONSUMPTION
// -----------------------------------------------------------------------------
$endTime = microtime(true);
$endMemory = memory_get_usage();
$peakMemory = memory_get_peak_usage();

$totalTime = round(($endTime - $startTime), 2);
$memoryMB = round(($endMemory - $startMemory) / 1024 / 1024, 2);
$peakMB = round($peakMemory / 1024 / 1024, 2);

echo "\n───────────────────────────────────────────────────────────────────\n";
echo "                     STRESS TEST COMPLETED                          \n";
echo "───────────────────────────────────────────────────────────────────\n";
echo "  Total Execution Time : {$totalTime} seconds\n";
echo "  Memory Used          : {$memoryMB} MB\n";
echo "  Peak Memory Usage    : {$peakMB} MB\n";
echo "───────────────────────────────────────────────────────────────────\n";
