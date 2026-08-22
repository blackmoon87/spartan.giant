<?php

declare(strict_types=1);

/**
 * Custom lightweight environment variable loader
 */
function loadEnv(string $path): void {
    if (!file_exists($path)) {
        return;
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || strpos($line, '#') === 0) {
            continue;
        }

        if (strpos($line, '=') === false) {
            continue;
        }

        list($name, $value) = explode('=', $line, 2);
        $name = trim($name);
        $value = trim($value);

        // Remove surrounding quotes if they exist
        if (preg_match('/^"(.*)"$/', $value, $matches)) {
            $value = $matches[1];
        } elseif (preg_match('/^\'(.*)\'$/', $value, $matches)) {
            $value = $matches[1];
        }

        if (!array_key_exists($name, $_SERVER) && !array_key_exists($name, $_ENV)) {
            putenv(sprintf('%s=%s', $name, $value));
            $_ENV[$name] = $value;
            $_SERVER[$name] = $value;
        }
    }
}

// Load env
loadEnv(dirname(__DIR__) . '/.env');

return [
    // Project root — every framework default (storage, views, migrations,
    // relative SQLite paths) resolves from here.
    'base_path' => dirname(__DIR__),

    'app' => [
        'name' => $_ENV['APP_NAME'] ?? 'PHP MVC Boilerplate',
        'env' => $_ENV['APP_ENV'] ?? 'production',
        'debug' => ($_ENV['APP_DEBUG'] ?? 'false') === 'true',
        'url' => $_ENV['APP_URL'] ?? 'http://localhost:8000',
        // Comma-separated proxy IPs/CIDRs allowed to set X-Forwarded-For.
        // Leave empty unless this app really sits behind a load balancer.
        'trusted_proxies' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) ($_ENV['TRUSTED_PROXIES'] ?? ''))
        ), fn(string $p): bool => $p !== '')),
    ],
    'db' => [
        'connection' => $_ENV['DB_CONNECTION'] ?? 'mysql',
        'host'       => $_ENV['DB_HOST']       ?? '127.0.0.1',
        'port'       => $_ENV['DB_PORT']       ?? '3306',
        'database'   => $_ENV['DB_DATABASE']   ?? '',
        'username'   => $_ENV['DB_USERNAME']   ?? 'root',
        'password'   => $_ENV['DB_PASSWORD']   ?? '',
        // Read/Write Splitting (opt-in — leave false for single-DB setups)
        'read_write_split' => ($_ENV['DB_READ_WRITE_SPLIT'] ?? 'false') === 'true',
        'read' => array_values(array_filter([
            !empty($_ENV['DB_READ_HOST_1']) ? ['host' => $_ENV['DB_READ_HOST_1'], 'port' => $_ENV['DB_READ_PORT_1'] ?? '3306'] : null,
            !empty($_ENV['DB_READ_HOST_2']) ? ['host' => $_ENV['DB_READ_HOST_2'], 'port' => $_ENV['DB_READ_PORT_2'] ?? '3306'] : null,
        ])),
    ],
    'cache' => [
        'driver'      => $_ENV['CACHE_DRIVER']   ?? 'file',
        'path'        => $_ENV['CACHE_PATH']     ?? dirname(__DIR__) . '/storage/cache',
        'redis_host'  => $_ENV['REDIS_HOST']     ?? '127.0.0.1',
        'redis_port'  => $_ENV['REDIS_PORT']     ?? '6379',
        'redis_password' => $_ENV['REDIS_PASSWORD'] ?? '',
        'redis_db'    => $_ENV['REDIS_DB']       ?? '0',
    ],
    'queue' => [
        'driver' => $_ENV['QUEUE_DRIVER'] ?? 'database',  // 'database' | 'redis'
    ],
    'logging' => [
        'format'    => $_ENV['LOG_FORMAT']  ?? 'text',     // 'text' | 'json'
        'channel'   => $_ENV['LOG_CHANNEL'] ?? 'app',
        'min_level' => $_ENV['LOG_LEVEL']   ?? 'DEBUG',
    ],
    'rate_limit' => [
        'default_limit'  => (int) ($_ENV['RATE_LIMIT_DEFAULT'] ?? 60),
        'default_window' => (int) ($_ENV['RATE_LIMIT_WINDOW'] ?? 60),
    ],
    'storage' => [
        'uploads' => $_ENV['UPLOAD_PATH'] ?? dirname(__DIR__) . '/public/uploads',
    ],
    'views' => [
        'cache_enabled' => ($_ENV['VIEW_CACHE_ENABLED'] ?? 'false') === 'true',
    ],
    'router' => [
        'cache_enabled' => ($_ENV['ROUTE_CACHE_ENABLED'] ?? 'false') === 'true',
        'cache_file'    => $_ENV['ROUTE_CACHE_FILE'] ?? dirname(__DIR__) . '/storage/cache/routes.php',
    ],
    'auth' => [
        'model' => 'App\\Models\\User',
    ],
    'locale' => [
        'default'  => $_ENV['APP_LOCALE'] ?? 'en',
        'fallback' => $_ENV['APP_FALLBACK_LOCALE'] ?? 'en',
        'rtl'      => ['ar', 'he', 'fa', 'ur'],
    ],
];
