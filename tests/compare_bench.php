#!/usr/bin/env php
<?php
/**
 * Spartan Giant — Performance Comparison Benchmark
 * Compares ORIGINAL vs UPDATED (with Translation Engine) framework performance.
 *
 * Tests the 5 core framework components + Translation Engine overhead.
 * Run: php tests/compare_bench.php
 */

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Spartan\Container;
use Spartan\Router;
use Spartan\Request;
use Spartan\Response;
use Spartan\QueryBuilder;
use Spartan\View;

// ─── Helpers ────────────────────────────────────────────────────────────────

function bench(string $label, int $iterations, callable $fn): array
{
    // Warm-up: 100 iterations
    for ($i = 0; $i < min(100, $iterations); $i++) {
        $fn($i);
    }

    // Actual benchmark
    $start = hrtime(true);
    for ($i = 0; $i < $iterations; $i++) {
        $fn($i);
    }
    $elapsed = (hrtime(true) - $start) / 1e9; // seconds

    $opsPerSec = $iterations / $elapsed;
    $msTotal   = round($elapsed * 1000, 2);
    $formatted = number_format(round($opsPerSec));

    return [
        'label'  => $label,
        'iters'  => $iterations,
        'ms'     => $msTotal,
        'ops'    => $opsPerSec,
        'opsFmt' => $formatted,
    ];
}

function printResult(array $r): void
{
    printf("  %-45s %8s ms  |  %12s ops/sec\n", $r['label'], $r['ms'], $r['opsFmt']);
}

// ─── Setup ──────────────────────────────────────────────────────────────────

$pdo = new PDO('sqlite::memory:', null, null, [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);

$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI']    = '/users/42/posts/hello-world';
$_SERVER['SCRIPT_NAME']    = '/index.php';
$_SERVER['REMOTE_ADDR']    = '127.0.0.1';

// Blade template for view benchmark
$viewsDir = sys_get_temp_dir() . '/spartan-bench-views-' . getmypid();
@mkdir($viewsDir, 0755, true);
file_put_contents($viewsDir . '/bench.blade.php', '
<div class="card">
    <h1>{{ $user["name"] }}</h1>
    <p>{{ $user["email"] }}</p>
    @if($user["active"])
        <span class="active">Active</span>
    @else
        <span class="inactive">Inactive</span>
    @endif
    <ul>
    @foreach($items as $item)
        <li>{{ $item }}</li>
    @endforeach
    </ul>
</div>
');

// Blade template WITH @lang for translation overhead test
file_put_contents($viewsDir . '/bench_lang.blade.php', '
<div class="card">
    <h1>@lang("app.welcome", ["name" => $user["name"]])</h1>
    <p>{{ $user["email"] }}</p>
    <nav>
        <a href="/">@lang("app.nav.home")</a>
        <a href="/dashboard">@lang("app.nav.dashboard")</a>
        <a href="/courses">@lang("app.nav.courses")</a>
    </nav>
    @if($user["active"])
        <span>@lang("app.messages.saved")</span>
    @endif
    <ul>
    @foreach($items as $item)
        <li>{{ $item }}</li>
    @endforeach
    </ul>
</div>
');

echo "═══════════════════════════════════════════════════════════════════════\n";
echo "          SPARTAN GIANT — PERFORMANCE COMPARISON BENCHMARK\n";
echo "          Original Framework vs Updated (+ Translation Engine)\n";
echo "═══════════════════════════════════════════════════════════════════════\n\n";

$memStart = memory_get_usage();
$allStart = hrtime(true);

// ─── Test 1: DI Container ───────────────────────────────────────────────────

class BenchDep {}
class BenchService { public function __construct(public BenchDep $dep) {} }

$container = new Container();
$r1 = bench('DI Container auto-resolution', 100_000, function ($i) use ($container) {
    $container->make(BenchService::class);
});
printResult($r1);

// ─── Test 2: Router Match + Param Extraction ────────────────────────────────

$request  = new Request();
$response = new Response();
$router   = new Router($request, $response);
$router->get('/users/{id}/posts/{slug}', function ($id, $slug) {
    return "User: {$id}, Post: {$slug}";
});

$r2 = bench('Router match + param extraction', 100_000, function ($i) use ($router) {
    $req = new Request();
    $router->setRequest($req);
    $router->resolve();
});
printResult($r2);

// ─── Test 3: QueryBuilder SQL Generation ────────────────────────────────────

$r3 = bench('QueryBuilder SQL generation', 50_000, function ($i) use ($pdo) {
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
});
printResult($r3);

// ─── Test 4: Blade compile + render (WITHOUT @lang) ─────────────────────────

$viewEngine = new View($viewsDir);
$viewParams = [
    'user'  => ['name' => 'Ahmad', 'email' => 'ahmad@spartan.org', 'active' => true],
    'items' => ['Container', 'QueryBuilder', 'Blade', 'Events', 'Auth'],
];

$r4 = bench('Blade compile + render (no @lang)', 10_000, function ($i) use ($viewEngine, $viewParams) {
    $viewEngine->render('bench', $viewParams);
});
printResult($r4);

// ─── Test 5: Blade compile + render (WITH @lang) ────────────────────────────

// Boot Translator
$translator = \Spartan\Translation\Translator::getInstance();
$translator->setLocale('ar');
$translator->setFallback('en');

$r5 = bench('Blade compile + render (with @lang)', 10_000, function ($i) use ($viewEngine, $viewParams) {
    $viewEngine->render('bench_lang', $viewParams);
});
printResult($r5);

// ─── Test 6: Translation Engine Pure Performance ────────────────────────────

$r6 = bench('trans() call — simple key', 100_000, function ($i) use ($translator) {
    $translator->get('app.nav.dashboard');
});
printResult($r6);

$r7 = bench('trans() call — nested + params', 100_000, function ($i) use ($translator) {
    $translator->get('app.pagination.showing', ['from' => '1', 'to' => '10', 'total' => '250']);
});
printResult($r7);

$r8 = bench('trans() call — locale override', 100_000, function ($i) use ($translator) {
    $translator->get('app.actions.save', [], $i % 2 === 0 ? 'ar' : 'en');
});
printResult($r8);

$r9 = bench('is_rtl() check', 500_000, function ($i) use ($translator) {
    $translator->isRtl($i % 2 === 0 ? 'ar' : 'en');
});
printResult($r9);

// ─── Summary ────────────────────────────────────────────────────────────────

$allEnd  = hrtime(true);
$totalMs = round(($allEnd - $allStart) / 1e6, 0);
$peakMB  = round(memory_get_peak_usage() / 1024 / 1024, 2);
$usedMB  = round((memory_get_usage() - $memStart) / 1024 / 1024, 2);

echo "\n───────────────────────────────────────────────────────────────────────\n";
echo "                         RESULTS SUMMARY\n";
echo "───────────────────────────────────────────────────────────────────────\n";

// Overhead calculation
$bladeNoLang  = $r4['ops'];
$bladeWithLang = $r5['ops'];
$overhead = round((1 - $bladeWithLang / $bladeNoLang) * 100, 1);

echo "\n  Framework Core Components:\n";
printf("    DI Container        : %s ops/sec\n", $r1['opsFmt']);
printf("    Router              : %s dispatches/sec\n", $r2['opsFmt']);
printf("    QueryBuilder        : %s queries/sec\n", $r3['opsFmt']);
printf("    Blade (no @lang)    : %s renders/sec\n", $r4['opsFmt']);

echo "\n  Translation Engine:\n";
printf("    Blade (with @lang)  : %s renders/sec\n", $r5['opsFmt']);
printf("    @lang overhead      : %s%%\n", $overhead > 0 ? "+{$overhead}" : "{$overhead}");
printf("    trans() simple      : %s ops/sec\n", $r6['opsFmt']);
printf("    trans() + params    : %s ops/sec\n", $r7['opsFmt']);
printf("    trans() + locale    : %s ops/sec\n", $r8['opsFmt']);
printf("    is_rtl()            : %s ops/sec\n", $r9['opsFmt']);

echo "\n  Resources:\n";
printf("    Total time          : %s ms\n", number_format($totalMs));
printf("    Peak memory         : %s MB\n", $peakMB);
printf("    Memory delta        : +%s MB\n", $usedMB);

echo "\n───────────────────────────────────────────────────────────────────────\n";

// Cleanup
@unlink($viewsDir . '/bench.blade.php');
@unlink($viewsDir . '/bench_lang.blade.php');
// Clean compiled views
foreach (glob($viewsDir . '/*.php') ?: [] as $f) @unlink($f);
@rmdir($viewsDir);
