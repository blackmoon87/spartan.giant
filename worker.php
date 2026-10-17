<?php

declare(strict_types=1);

/**
 * Async Job Queue Worker
 *
 * Processes pending jobs from the `jobs` table.
 * Must be run from the CLI (not via browser).
 *
 * ─── Usage ───────────────────────────────────────────────────────────────────
 *
 *   # Run once — process all pending jobs and exit (ideal for Cron):
 *   php worker.php
 *
 *   # Run continuously — poll every 5 seconds (ideal for development / daemon):
 *   php worker.php --loop
 *
 * ─── Cron Example (every minute) ─────────────────────────────────────────────
 *
 *   * * * * * php /var/www/bunzz/MVC.Zero/worker.php >> /var/log/mvc_worker.log 2>&1
 *
 * ─── Prerequisites ────────────────────────────────────────────────────────────
 *   1. Run `php spartan migrate` to create the jobs table.
 *   2. Configure DB credentials in .env.
 * ─────────────────────────────────────────────────────────────────────────────
 */

// ── Guard: only allow CLI execution ──────────────────────────────────────────
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Worker must be run from the command line.');
}

// ── Bootstrap ─────────────────────────────────────────────────────────────────
$autoloadPath = __DIR__ . '/vendor/autoload.php';
$hasVendor    = file_exists($autoloadPath);
if ($hasVendor) {
    require_once $autoloadPath;
}

// PSR-4 fallback. The application namespace is always registered: composer.json
// maps App\ only under autoload-dev (this repository is also the framework
// package), so after `composer install --no-dev` Composer cannot find App\
// classes. The framework is mapped too when vendor/ is absent. Composer's own
// loader runs first; this only handles classes it could not find.
$prefixes = ['App\\' => __DIR__ . '/src/'];
if (!$hasVendor) {
    $prefixes['Spartan\\'] = __DIR__ . '/framework/src/';
}
spl_autoload_register(function (string $class) use ($prefixes): void {
    foreach ($prefixes as $prefix => $baseDir) {
        if (strncmp($prefix, $class, strlen($prefix)) !== 0) {
            continue;
        }
        $file = $baseDir . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
        if (is_file($file)) {
            require_once $file;
        }
        return;
    }
});
if (!$hasVendor) {
    require_once __DIR__ . '/framework/src/helpers.php';
}

use Spartan\Application;
use Spartan\JobQueue;

$config = require __DIR__ . '/config/config.php';
$app    = new Application($config);

// ── Validate DB is available ───────────────────────────────────────────────────
if ($app->db === null) {
    fwrite(STDERR, "[Worker] No database connection. Check .env DB credentials.\n");
    exit(1);
}

$queue     = new JobQueue($app->db, (int) ($config['queue']['stale_after'] ?? 600));
$loopMode  = in_array('--loop', $argv ?? [], true);
$pollSecs  = 5;

// ── Graceful Shutdown ──────────────────────────────────────────────────────────
$shouldStop = false;

if (function_exists('pcntl_signal')) {
    pcntl_signal(SIGTERM, function () use (&$shouldStop) {
        echo "\n[Worker] SIGTERM received — finishing current job and shutting down...\n";
        $shouldStop = true;
    });
    pcntl_signal(SIGINT, function () use (&$shouldStop) {
        echo "\n[Worker] SIGINT received — finishing current job and shutting down...\n";
        $shouldStop = true;
    });
}

// ── Run ────────────────────────────────────────────────────────────────────────
echo "[Worker] Started" . ($loopMode ? " in loop mode (polling every {$pollSecs}s)" : '') . ".\n";

do {
    // Dispatch pending signals (SIGTERM, SIGINT)
    if (function_exists('pcntl_signal_dispatch')) {
        pcntl_signal_dispatch();
    }

    if ($shouldStop) {
        break;
    }

    $processed = $queue->processPending();

    if ($processed > 0) {
        echo "[Worker] " . date('Y-m-d H:i:s') . " — processed {$processed} job(s).\n";
    }

    if ($loopMode && !$shouldStop) {
        sleep($pollSecs);
    }

} while ($loopMode && !$shouldStop);

echo "[Worker] Shut down gracefully.\n";
