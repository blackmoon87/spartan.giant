<?php

/**
 * PHPUnit bootstrap.
 *
 * Spartan's Application is a per-process singleton, so it is booted exactly
 * once here and shared by every test case. The database is a throwaway SQLite
 * file under the system temp directory; storage paths are redirected there too
 * so a test run never writes into the project.
 */

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Spartan\Application;
use Spartan\Paths;

$work = sys_get_temp_dir() . '/spartan-tests-' . getmypid();
foreach (['', '/cache', '/logs', '/views', '/tpl'] as $sub) {
    @mkdir($work . $sub, 0777, true);
}

// FormRequest calls exit() on failure unless this is set.
if (!defined('SPARTAN_TESTING')) {
    define('SPARTAN_TESTING', true);
}

$GLOBALS['SPARTAN_TEST_WORKDIR'] = $work;

// Framework components report recoverable problems through error_log(); keep
// that out of the test report while still capturing it for inspection.
ini_set('error_log', $work . '/php-error.log');

$_SERVER['REQUEST_URI']    = '/';
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['SCRIPT_NAME']    = '/index.php';
$_SERVER['REMOTE_ADDR']    = '127.0.0.1';

Paths::setBase($work);

$app = new Application([
    'base_path' => $work,
    'app'   => ['name' => 'Spartan', 'env' => 'testing', 'debug' => false, 'url' => 'http://localhost:8000', 'trusted_proxies' => []],
    'db'    => ['connection' => 'sqlite', 'database' => $work . '/test.sqlite'],
    'cache' => ['driver' => 'file', 'path' => $work . '/cache'],
    'views' => ['cache_enabled' => false, 'cache_path' => $work . '/views'],
    'router'=> ['cache_enabled' => false, 'cache_file' => $work . '/routes.php'],
    'auth'  => ['model' => \Spartan\Tests\Fixtures\UserModel::class],
    'rate_limit' => ['default_limit' => 60, 'default_window' => 60],
]);

$GLOBALS['SPARTAN_TEST_APP'] = $app;

// Framework schema + fixture tables.
ob_start();
(new \Spartan\Database\Migrator($app->db, __DIR__ . '/../database/migrations'))->migrate();
ob_end_clean();

$app->db->exec("
    CREATE TABLE t_users (
        id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT, email TEXT, role TEXT DEFAULT 'user',
        active INTEGER DEFAULT 1, score INTEGER DEFAULT 0, created_at DATETIME, updated_at DATETIME
    );
    CREATE TABLE t_posts (
        id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER, title TEXT, views INTEGER DEFAULT 0,
        created_at DATETIME, updated_at DATETIME
    );
");

register_shutdown_function(function () use ($work): void {
    $rm = function (string $dir) use (&$rm): void {
        foreach (glob($dir . '/*') ?: [] as $f) {
            is_dir($f) ? $rm($f) : @unlink($f);
        }
        @rmdir($dir);
    };
    $rm($work);
});
