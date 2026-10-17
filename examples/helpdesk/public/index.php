<?php

declare(strict_types=1);

// Built-in web server: let it serve real files (css, favicon) directly.
if (php_sapi_name() === 'cli-server') {
    $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    if (is_string($path) && is_file(__DIR__ . $path)) {
        return false;
    }
}

// ─── Autoloading ──────────────────────────────────────────────────────────────
// With `composer install` the framework comes from vendor/. Without it, this
// example loads the framework straight from the repository (../../framework),
// so it runs with zero setup.
$autoloadPath = dirname(__DIR__) . '/vendor/autoload.php';
$hasVendor    = file_exists($autoloadPath);
if ($hasVendor) {
    require_once $autoloadPath;
}
$prefixes = ['App\\' => dirname(__DIR__) . '/src/'];
if (!$hasVendor) {
    $prefixes['Spartan\\'] = dirname(__DIR__, 3) . '/framework/src/';
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
    require_once dirname(__DIR__, 3) . '/framework/src/helpers.php';
}

use App\ApiAwareExceptionHandler;
use App\Listeners\RecordActivity;
use App\Listeners\SendNotification;
use App\Middlewares\ApiTokenAuth;
use App\Middlewares\ShareNavData;
use App\Models\Ticket;
use App\Policies\TicketPolicy;
use Spartan\Application;
use Spartan\Database\Migrator;
use Spartan\Gate;
use Spartan\Middlewares\AuthMiddleware;
use Spartan\Middlewares\CsrfMiddleware;
use Spartan\Middlewares\RateLimitMiddleware;
use Spartan\Middlewares\SecurityHeadersMiddleware;

$config = require_once dirname(__DIR__) . '/config/config.php';
$config['base_path'] ??= dirname(__DIR__);

$debug = (bool) ($config['app']['debug'] ?? false);
ini_set('display_errors', $debug ? '1' : '0');
error_reporting($debug ? E_ALL : E_ALL & ~E_NOTICE & ~E_DEPRECATED);

// ─── First run: build the SQLite demo database ───────────────────────────────
// Built from database/migrations + database/seed.sql into a temp file, then
// renamed into place, so a concurrent request never opens a half-built one.
// Afterwards this costs one is_file() check.
if (($config['db']['connection'] ?? '') === 'sqlite' && ($config['db']['database'] ?? '') !== '') {
    $dbFile = str_starts_with($config['db']['database'], '/')
        ? $config['db']['database']
        : dirname(__DIR__) . '/' . $config['db']['database'];

    if (!is_file($dbFile)) {
        $tmp = $dbFile . '.' . getmypid() . '.building';
        $pdo = new PDO('sqlite:' . $tmp, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        ob_start();
        (new Migrator($pdo, dirname(__DIR__) . '/database/migrations'))->migrate();
        ob_end_clean();
        $pdo->exec((string) file_get_contents(dirname(__DIR__) . '/database/seed.sql'));
        $pdo = null;
        rename($tmp, $dbFile);
    }
}

$app = new Application($config);

// ─── Middleware ───────────────────────────────────────────────────────────────
// Global: security headers (CSP default-src 'self' — the CSS is a local file,
// no inline styles or scripts anywhere), CSRF on every state-changing
// request, and the nav badge data.
$app->router->setGlobalMiddlewares([
    SecurityHeadersMiddleware::class,
    CsrfMiddleware::class,
    ShareNavData::class,
]);
$app->router->aliasMiddleware('auth', AuthMiddleware::class);
$app->router->aliasMiddleware('throttle', RateLimitMiddleware::class);
$app->router->aliasMiddleware('api.auth', ApiTokenAuth::class);

// ─── Authorization ────────────────────────────────────────────────────────────
Gate::policy(Ticket::class, TicketPolicy::class);

// ─── Events ───────────────────────────────────────────────────────────────────
// History is written immediately; notifications go through the queue —
// run `php spartan worker --loop` to deliver them.
$app->events->listen('ticket.activity', RecordActivity::class);
$app->events->listen('ticket.notify', SendNotification::class, async: true, maxAttempts: 3);

// ─── Errors: JSON for /api/*, HTML pages elsewhere ───────────────────────────
$app->container->singleton(\Spartan\ExceptionHandler::class, fn () => new ApiAwareExceptionHandler());

// ─── Routes ───────────────────────────────────────────────────────────────────
require_once dirname(__DIR__) . '/routes/web.php';
require_once dirname(__DIR__) . '/routes/api.php';

// ─── Run (standard PHP or FrankenPHP worker mode) ────────────────────────────
if (($config['frankenphp_worker'] ?? env('FRANKENPHP_WORKER', false)) && function_exists('frankenphp_handle_request')) {
    $maxRequests = (int) env('FRANKENPHP_MAX_REQUESTS', 1000);
    for ($n = 0; frankenphp_handle_request(fn () => $app->handleRequest()); $n++) {
        if ($maxRequests > 0 && $n >= $maxRequests) {
            break;
        }
    }
} else {
    $app->run();
}
