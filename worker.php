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
 *   1. Run storage/jobs.sql to create the jobs table.
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
if (file_exists($autoloadPath)) {
    require_once $autoloadPath;
} else {
    // Fallback PSR-4 autoloader (mirrors public/index.php)
    spl_autoload_register(function (string $class): void {
        $prefix  = 'App\\';
        $baseDir = __DIR__ . '/src/';
        $len     = strlen($prefix);
        if (strncmp($prefix, $class, $len) !== 0) {
            return;
        }
        $file = $baseDir . str_replace('\\', '/', substr($class, $len)) . '.php';
        if (file_exists($file)) {
            require_once $file;
        }
    });
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

$queue     = new JobQueue($app->db);
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
