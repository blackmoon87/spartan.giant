<?php

declare(strict_types=1);



// Fallback PSR-4 autoloader so the example runs before `composer install`.
    // In a real project `composer require spartan/framework` provides both.
    $prefixes = [
        'Spartan\\' => dirname(__DIR__, 3) . '/framework/src/',
        'App\\'     => dirname(__DIR__) . '/src/',
    ];

    spl_autoload_register(function (string $class) use ($prefixes): void {
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

    require_once dirname(__DIR__, 3) . '/framework/src/helpers.php';

$config = require __DIR__ . '/../config/config.php';
$config['base_path'] ??= dirname(__DIR__);
$app = new Spartan\Application($config);

require_once __DIR__ . '/../routes/web.php';

$app->run();
