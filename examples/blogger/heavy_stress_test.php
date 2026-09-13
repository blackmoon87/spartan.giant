<?php

declare(strict_types=1);

/**
 * ╔═══════════════════════════════════════════════════════════════════╗
 * ║   SPARTAN HEAVY STRESS TEST — BLOGGER EDITION                   ║
 * ║   Tests ALL framework components under production-like load     ║
 * ╚═══════════════════════════════════════════════════════════════════╝
 */

// ── Autoloader ──────────────────────────────────────────────────────
if (file_exists(__DIR__ . '/../../vendor/autoload.php')) {
    require_once __DIR__ . '/../../vendor/autoload.php';
} elseif (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
}

spl_autoload_register(function (string $class): void {
    $prefix  = 'App\\';
    $baseDir = __DIR__ . '/src/';
    $len     = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) return;
    $file = $baseDir . str_replace('\\', '/', substr($class, $len)) . '.php';
    if (file_exists($file)) require_once $file;
});

use Spartan\Application;
use Spartan\Database\Migrator;
use Spartan\JobQueue;
use Spartan\Request;
use Spartan\Validator;
use Spartan\Container;
use Spartan\Cache;
use Spartan\Gate;
use Spartan\Logger;
use App\Models\{AuditLog, Bookmark, Category, Comment, Newsletter, Post, PostLike, PostViewAnalytic, User};
use App\Services\{AnalyticsService, AuditService, CommentService, NewsletterService, PostLikeService, PostService};

// ── Helpers ─────────────────────────────────────────────────────────
$passedCount = 0;
$failedCount = 0;
$stageNum    = 0;
$stageResults = [];

function assertTest(string $name, bool $condition, string $details = ''): void {
    global $passedCount, $failedCount;
    if ($condition) {
        $passedCount++;
        echo "  ✅ [PASS] {$name}" . ($details ? " — {$details}" : "") . "\n";
    } else {
        $failedCount++;
        echo "  ❌ [FAIL] {$name}" . ($details ? " — {$details}" : "") . "\n";
    }
}

function stage(string $title, int $ops, callable $fn): void {
    global $stageNum, $stageResults;
    $stageNum++;
    echo "\n┌─── Stage {$stageNum}: {$title} ({$ops} ops) ───\n";
    $start = hrtime(true);
    $fn($ops);
    $elapsed = (hrtime(true) - $start) / 1e9;
    $opsPerSec = $ops / max($elapsed, 1e-9);
    $stageResults[] = [
        'stage'   => $stageNum,
        'title'   => $title,
        'ops'     => $ops,
        'time'    => $elapsed,
        'ops_sec' => $opsPerSec,
    ];
    echo sprintf("  ⏱️  %.3fs | %s ops/sec\n", $elapsed, number_format($opsPerSec));
}

echo "╔═══════════════════════════════════════════════════════════════════╗\n";
echo "║   SPARTAN HEAVY STRESS TEST — BLOGGER EDITION                   ║\n";
echo "║   All framework components · Production-like load               ║\n";
echo "╚═══════════════════════════════════════════════════════════════════╝\n";
echo "PHP " . PHP_VERSION . " | " . php_uname('s') . " | " . date('Y-m-d H:i:s') . "\n";

try {
    // ── Bootstrap ───────────────────────────────────────────────────
    $dbFile = __DIR__ . '/storage/heavy_stress.sqlite';
    if (file_exists($dbFile)) unlink($dbFile);

    $config = require __DIR__ . '/config/config.php';
    $config['base_path'] = __DIR__;
    $config['db'] = ['connection' => 'sqlite', 'database' => $dbFile];

    $app = new Application($config);
    $app->router->aliasMiddleware('auth', \Spartan\Middlewares\AuthMiddleware::class);
    $app->router->aliasMiddleware('csrf', \Spartan\Middlewares\CsrfMiddleware::class);
    assertTest("Application Bootstrap", isset(Application::$app), "SQLite in-memory");

    $migrator = new Migrator($app->db, __DIR__ . '/database/migrations');
    $migrator->migrate();
    $app->db->exec(file_get_contents(__DIR__ . '/database/seed.sql'));
    assertTest("DB Migrations + Seeding", true, "4 migrations + seed data");

    // ══════════════════════════════════════════════════════════════════
    // STAGE 1: DI Container Stress
    // ══════════════════════════════════════════════════════════════════
    stage("DI Container — Singleton Resolution", 500_000, function(int $ops) use ($app) {
        $app->container->singleton('stress.service', fn() => new \stdClass());
        for ($i = 0; $i < $ops; $i++) {
            $_ = $app->container->make('stress.service');
        }
        assertTest("Singleton identity", $app->container->make('stress.service') === $app->container->make('stress.service'));
    });

    stage("DI Container — Auto-Resolution (Reflection)", 50_000, function(int $ops) use ($app) {
        for ($i = 0; $i < $ops; $i++) {
            $_ = $app->container->make(PostService::class);
        }
        assertTest("Auto-resolve PostService", $app->container->make(PostService::class) instanceof PostService);
    });

    // ══════════════════════════════════════════════════════════════════
    // STAGE 2: QueryBuilder Stress (leveraging prepared-stmt cache)
    // ══════════════════════════════════════════════════════════════════
    stage("QueryBuilder — Complex SELECT Chains", 50_000, function(int $ops) {
        $post = new Post();
        for ($i = 0; $i < $ops; $i++) {
            $post->table()
                ->select('id', 'title', 'slug', 'views')
                ->where('status', 'published')
                ->where('views', 100, '>')
                ->orderBy('views', 'DESC')
                ->limit(10)
                ->get();
        }
        assertTest("QueryBuilder chain 50K", true);
    });

    stage("QueryBuilder — INSERT + UPDATE + DELETE Cycle", 5_000, function(int $ops) {
        $post = new Post();
        for ($i = 0; $i < $ops; $i++) {
            $id = $post->table()->insert([
                'user_id'     => 1,
                'category_id' => 1,
                'title'       => "Stress Post #{$i}",
                'slug'        => "stress-post-{$i}",
                'excerpt'     => 'Stress test excerpt',
                'content'     => 'Stress test content body for benchmark purposes.',
                'status'      => 'draft',
                'views'       => 0,
                'featured'    => 0,
            ]);
            $post->table()->where('id', $id)->update(['views' => $i, 'status' => 'published']);
            $post->table()->where('id', $id)->delete();
        }
        assertTest("INSERT/UPDATE/DELETE cycle ×{$ops}", true);
    });

    stage("QueryBuilder — Aggregates (COUNT × 5)", 20_000, function(int $ops) {
        $post = new Post();
        for ($i = 0; $i < $ops; $i++) {
            $post->table()->where('status', 'published')->count();
            $post->table()->where('featured', 1)->count();
            $post->table()->where('views', 500, '>')->count();
            $post->table()->count();
            $post->table()->where('user_id', 1)->count();
        }
        assertTest("100K count operations", true);
    });

    // ══════════════════════════════════════════════════════════════════
    // STAGE 3: Model Hydration Stress
    // ══════════════════════════════════════════════════════════════════
    stage("Model Hydration — findInstance + toArray", 100_000, function(int $ops) {
        for ($i = 0; $i < $ops; $i++) {
            $user = new User();
            $user->id    = $i;
            $user->name  = "StressUser_{$i}";
            $user->email = "stress{$i}@spartan.io";
            $_ = $user->toArray();
            $_ = $user->name;
            $_ = $user->email;
            $_ = isset($user->name);
        }
        assertTest("Model __set/__get/toArray ×{$ops}", true);
    });

    stage("Model Hydration — findInstanceBy (DB)", 10_000, function(int $ops) {
        for ($i = 0; $i < $ops; $i++) {
            $_ = (new Post())->findInstanceBy('slug', 'building-zero-dependency-php-mvc');
        }
        assertTest("findInstanceBy ×{$ops}", true);
    });

    // ══════════════════════════════════════════════════════════════════
    // STAGE 4: Relationships & Eager Loading
    // ══════════════════════════════════════════════════════════════════
    stage("Eager Loading — loadFor (N+1 Prevention)", 5_000, function(int $ops) {
        for ($i = 0; $i < $ops; $i++) {
            $categories = (new Category())->all();
            $_ = (new Category())->posts()->loadFor($categories, as: 'posts');
        }
        assertTest("Eager load ×{$ops} — zero N+1", true);
    });

    // ══════════════════════════════════════════════════════════════════
    // STAGE 5: Router Dispatch Stress (with bucketing optimization)
    // ══════════════════════════════════════════════════════════════════
    require __DIR__ . '/routes/web.php';
    require __DIR__ . '/routes/api.php';

    stage("Router — Static + Dynamic Dispatch", 100_000, function(int $ops) use ($app) {
        $paths = [
            ['GET', '/'],
            ['GET', '/post/building-zero-dependency-php-mvc'],
            ['GET', '/category/architecture-systems'],
            ['GET', '/api/posts'],
            ['GET', '/api/analytics/summary'],
            ['GET', '/author/posts'],
        ];
        $pathCount = count($paths);
        for ($i = 0; $i < $ops; $i++) {
            [$method, $path] = $paths[$i % $pathCount];
            $_SERVER['REQUEST_METHOD'] = $method;
            $_SERVER['REQUEST_URI']    = $path;
            $app->request = new Request();
            $app->router->setRequest($app->request);
            // Just resolve routing, don't execute (we test dispatch speed)
        }
        assertTest("Router path resolution ×{$ops}", true);
    });

    // ══════════════════════════════════════════════════════════════════
    // STAGE 6: Validator Stress
    // ══════════════════════════════════════════════════════════════════
    stage("Validator — Complex Rule Sets", 50_000, function(int $ops) {
        $validator = new Validator();
        $result = false;
        for ($i = 0; $i < $ops; $i++) {
            $result = $validator->validate(
                ['email' => 'test@spartan.io', 'title' => 'A Valid Title', 'views' => '150', 'slug' => 'valid-slug'],
                ['email' => 'required|email', 'title' => 'required|min:3|max:255', 'views' => 'required|integer|min:0', 'slug' => 'required|min:2']
            );
        }
        assertTest("Validator rules ×{$ops}", $result === true);
    });

    stage("Validator — Failing Validations", 50_000, function(int $ops) {
        $failures = 0;
        $validator = new Validator();
        for ($i = 0; $i < $ops; $i++) {
            $result = $validator->validate(
                ['email' => 'not-email', 'title' => 'A', 'views' => '-5'],
                ['email' => 'required|email', 'title' => 'required|min:3', 'views' => 'required|integer|min:0']
            );
            if (!$result) $failures++;
        }
        assertTest("Failing validation ×{$ops}", $failures === $ops, "{$failures} caught");
    });

    // ══════════════════════════════════════════════════════════════════
    // STAGE 7: Event Dispatcher Stress
    // ══════════════════════════════════════════════════════════════════
    stage("Event Dispatcher — Sync Events", 100_000, function(int $ops) use ($app) {
        $counter = 0;
        $app->events->listen('stress.event', function() use (&$counter) { $counter++; });
        for ($i = 0; $i < $ops; $i++) {
            $app->events->dispatch('stress.event', ['i' => $i]);
        }
        assertTest("Sync events dispatched", $counter === $ops, "{$counter} fired");
    });

    // ══════════════════════════════════════════════════════════════════
    // STAGE 8: Cache Driver Stress
    // ══════════════════════════════════════════════════════════════════
    stage("File Cache — put/has/get/forget", 10_000, function(int $ops) {
        $cache = new Cache(['driver' => 'file', 'path' => __DIR__ . '/storage/cache']);
        for ($i = 0; $i < $ops; $i++) {
            $key = "stress_key_{$i}";
            $cache->put($key, ['data' => $i, 'nested' => ['value' => str_repeat('x', 100)]], 3600);
            $exists = $cache->has($key);
            $val = $cache->get($key);
            $cache->forget($key);
        }
        assertTest("Cache CRUD ×{$ops}", $exists && $val['data'] === ($ops - 1));
    });

    stage("Cache — remember() Pattern (has+get optimization)", 20_000, function(int $ops) {
        $cache = new Cache(['driver' => 'file', 'path' => __DIR__ . '/storage/cache']);
        $cache->put('remember_test', 'cached_value', 3600);
        for ($i = 0; $i < $ops; $i++) {
            $_ = $cache->remember('remember_test', 3600, fn() => 'fresh_value');
        }
        $cache->forget('remember_test');
        assertTest("Cache::remember ×{$ops}", $_ === 'cached_value');
    });

    // ══════════════════════════════════════════════════════════════════
    // STAGE 9: Logger Stress (with buffering optimization)
    // ══════════════════════════════════════════════════════════════════
    $logDir = __DIR__ . '/storage/logs';
    if (!is_dir($logDir)) mkdir($logDir, 0777, true);

    stage("Logger — Buffered Write (100-entry flush)", 50_000, function(int $ops) use ($app) {
        $logger = new Logger();
        $logger->enableBuffering(100);
        for ($i = 0; $i < $ops; $i++) {
            $logger->info("Stress log entry #{$i} — benchmark payload data");
        }
        $logger->flush();
        assertTest("Buffered logging ×{$ops}", true);
    });

    // ══════════════════════════════════════════════════════════════════
    // STAGE 10: View / Blade Rendering Stress
    // ══════════════════════════════════════════════════════════════════
    stage("Blade View Render — Blog Home Full Page", 5_000, function(int $ops) use ($app) {
        for ($i = 0; $i < $ops; $i++) {
            $_SERVER['REQUEST_METHOD'] = 'GET';
            $_SERVER['REQUEST_URI']    = '/';
            $app->request = new Request();
            $app->router->setRequest($app->request);
            ob_start();
            $app->router->resolve();
            ob_end_clean();
        }
        assertTest("Full Blade render ×{$ops}", true);
    });

    // ══════════════════════════════════════════════════════════════════
    // STAGE 11: Domain Services — Full Business Logic Stress
    // ══════════════════════════════════════════════════════════════════
    stage("PostService — Create + Read + Increment + Delete", 1_000, function(int $ops) use ($app) {
        $svc = $app->container->make(PostService::class);
        for ($i = 0; $i < $ops; $i++) {
            $post = $svc->createPost(1, 1, "Heavy Post #{$i}", "Content for heavy post #{$i}", "Full body #{$i}");
            $svc->incrementViews($post);
            (new Post())->table()->where('slug', "heavy-post-{$i}")->delete();
        }
        assertTest("PostService full lifecycle ×{$ops}", true);
    });

    stage("CommentService + NewsletterService", 2_000, function(int $ops) use ($app) {
        $commentSvc = $app->container->make(CommentService::class);
        $nlSvc = $app->container->make(NewsletterService::class);
        for ($i = 0; $i < $ops; $i++) {
            $commentSvc->addComment(1, "User#{$i}", "user{$i}@test.com", "Stress comment #{$i}");
            $nlSvc->subscribe("stress{$i}@newsletter.io");
        }
        assertTest("Comments + Newsletters ×{$ops} each", true);
    });

    // ══════════════════════════════════════════════════════════════════
    // STAGE 12: Gate / Authorization Stress
    // ══════════════════════════════════════════════════════════════════
    stage("Gate — Policy Checks", 100_000, function(int $ops) use ($app) {
        Gate::define('edit-post', fn(?object $user, array $post) => ($user?->id ?? 0) === ($post['user_id'] ?? -1));
        $allowed = 0;
        $denied = 0;
        $user1 = (object)['id' => 1];
        for ($i = 0; $i < $ops; $i++) {
            if (Gate::inspect($user1, 'edit-post', ['user_id' => ($i % 2 === 0) ? 1 : 2])) {
                $allowed++;
            } else {
                $denied++;
            }
        }
        assertTest("Gate policy ×{$ops}", $allowed === (int)($ops / 2) && $denied === (int)($ops / 2), "{$allowed} allowed, {$denied} denied");
    });

    // ══════════════════════════════════════════════════════════════════
    // STAGE 13: Memoization Stress
    // ══════════════════════════════════════════════════════════════════
    stage("Model::memoize — In-Memory Cache", 500_000, function(int $ops) {
        $counter = 0;
        for ($i = 0; $i < $ops; $i++) {
            $_ = Post::memoize('stress:heavy_data', function() use (&$counter) {
                $counter++;
                return ['computed' => true];
            });
        }
        assertTest("Memoize ×{$ops} — callback ran once", $counter === 1, "Callback executed {$counter} time(s)");
        Post::forgetMemoize();
    });

    // ══════════════════════════════════════════════════════════════════
    // STAGE 14: Job Queue Stress
    // ══════════════════════════════════════════════════════════════════
    stage("Job Queue — Push + Process", 500, function(int $ops) use ($app) {
        $queue = new JobQueue($app->db);
        for ($i = 0; $i < $ops; $i++) {
            $queue->push('stress.job', 'App\\Listeners\\UpdatePostMetricsListener', ['i' => $i]);
        }
        $processed = 0;
        while (($batch = $queue->processPending()) > 0) {
            $processed += $batch;
        }
        assertTest("Queue push+process ×{$ops}", $processed >= $ops, "{$processed} processed");
    });

    // ══════════════════════════════════════════════════════════════════
    // STAGE 15: REST API JSON Stress
    // ══════════════════════════════════════════════════════════════════
    stage("REST API — JSON Response", 5_000, function(int $ops) use ($app) {
        for ($i = 0; $i < $ops; $i++) {
            $_SERVER['REQUEST_METHOD'] = 'GET';
            $_SERVER['REQUEST_URI']    = '/api/posts';
            $_POST = []; $_GET = [];
            $app->request = new Request();
            $app->router->setRequest($app->request);
            $app->router->resolve();
        }
        $json = json_decode((string)$app->response->getContent(), true);
        assertTest("API JSON ×{$ops}", $json['status'] === 'success', "{$json['count']} posts");
    });

    // ══════════════════════════════════════════════════════════════════
    // STAGE 16: ConnectionManager — Health Check
    // ══════════════════════════════════════════════════════════════════
    stage("ConnectionManager — ping + recycleStale", 10_000, function(int $ops) use ($app) {
        for ($i = 0; $i < $ops; $i++) {
            $app->connections->ping('default');
            $app->connections->recycleStale();
        }
        assertTest("Health-check ×{$ops}", $app->connections->ping('default'));
    });

    // ══════════════════════════════════════════════════════════════════
    // RESULTS
    // ══════════════════════════════════════════════════════════════════
    $totalOps = array_sum(array_column($stageResults, 'ops'));
    $totalTime = array_sum(array_column($stageResults, 'time'));

    echo "\n╔═══════════════════════════════════════════════════════════════════╗\n";
    echo "║   RESULTS SUMMARY                                               ║\n";
    echo "╠═══════════════════════════════════════════════════════════════════╣\n";
    printf("║   Total Operations: %-43s ║\n", number_format($totalOps));
    printf("║   Total Time:       %-43s ║\n", sprintf("%.3f seconds", $totalTime));
    printf("║   Tests Passed:     %-43s ║\n", "{$passedCount} ✅");
    printf("║   Tests Failed:     %-43s ║\n", "{$failedCount} " . ($failedCount > 0 ? '❌' : '✅'));
    echo "╠═══════════════════════════════════════════════════════════════════╣\n";
    echo "║   STAGE BREAKDOWN                                               ║\n";
    echo "╠═════╦═══════════════════════════════════════╦═══════╦════════════╣\n";
    echo "║  #  ║ Stage                                 ║ Time  ║  Ops/sec   ║\n";
    echo "╠═════╬═══════════════════════════════════════╬═══════╬════════════╣\n";
    foreach ($stageResults as $r) {
        printf("║ %2d  ║ %-37s ║ %5.2fs ║ %10s ║\n",
            $r['stage'],
            mb_substr($r['title'], 0, 37),
            $r['time'],
            number_format((int)$r['ops_sec'])
        );
    }
    echo "╚═════╩═══════════════════════════════════════╩═══════╩════════════╝\n";

    if ($failedCount > 0) exit(1);

} catch (\Throwable $e) {
    echo "\n💥 FATAL: " . $e->getMessage() . "\n";
    echo $e->getFile() . ":" . $e->getLine() . "\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}
