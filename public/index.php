<?php

declare(strict_types=1);

// Support PHP built-in web server static files
if (php_sapi_name() === 'cli-server') {
    $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    $file = __DIR__ . $path;
    if (is_file($file)) {
        return false;
    }
}

// Load Autoloader
$autoloadPath = dirname(__DIR__) . '/vendor/autoload.php';
$hasVendor    = file_exists($autoloadPath);
if ($hasVendor) {
    require_once $autoloadPath;
}

// PSR-4 fallback. The application namespace is always registered: composer.json
// maps App\ only under autoload-dev (this repository is also the framework
// package), so after `composer install --no-dev` Composer cannot find App\
// classes. The framework is mapped too when vendor/ is absent. Composer's own
// loader runs first; this only handles classes it could not find.
$prefixes = ['App\\' => dirname(__DIR__) . '/src/'];
if (!$hasVendor) {
    $prefixes['Spartan\\'] = dirname(__DIR__) . '/framework/src/';
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
    require_once dirname(__DIR__) . '/framework/src/helpers.php';
}

use Spartan\Application;

// Load config array (this also loads .env)
$config = require_once dirname(__DIR__) . '/config/config.php';

// Error reporting — controlled strictly by APP_DEBUG. Read AFTER the config
// loads .env: checked earlier, APP_DEBUG from .env was never seen.
// NEVER enable display_errors in production.
$debugMode = (bool) ($config['app']['debug'] ?? false);
if ($debugMode) {
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
    error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED);
}

// Tell the framework where the project lives (it ships from vendor/).
$config['base_path'] ??= dirname(__DIR__);

// Instantiate App
$app = new Application($config);

// CSRF verification for every state-changing request (POST/PUT/PATCH/DELETE).
// Exclude stateless endpoints with $app->router->excludeCsrf('/api/*').
$app->router->setGlobalMiddlewares([
    \Spartan\Middlewares\CsrfMiddleware::class,
]);

// ─── Load Routes ──────────────────────────────────────────────────────────────
// If route cache is enabled and not in debug mode, load from cache.
$routesPath = dirname(__DIR__) . '/routes';
$routeCacheEnabled = $config['router']['cache_enabled'] ?? false;

if ($routeCacheEnabled && !$debugMode && $app->router->loadCache()) {
    // Loaded from cache
} else {
    require_once $routesPath . '/web.php';
    require_once $routesPath . '/admin.php';
    require_once $routesPath . '/api.php';

    if ($routeCacheEnabled && !$debugMode) {
        $app->router->saveCache();
    }
}

// Start the Application (Supports standard mode and FrankenPHP Worker Mode)
if (($config['frankenphp_worker'] ?? env('FRANKENPHP_WORKER', false)) && function_exists('frankenphp_handle_request')) {
    $handler = function () use ($app) {
        $app->handleRequest();
    };
    $maxRequests = (int) (env('FRANKENPHP_MAX_REQUESTS', 1000));
    for ($nbRequests = 0; frankenphp_handle_request($handler); $nbRequests++) {
        if ($maxRequests > 0 && $nbRequests >= $maxRequests) {
            break; // Auto-recycle worker to prevent memory leak
        }
    }
} else {
    $app->run();
}
