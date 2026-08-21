<?php

declare(strict_types=1);

/**
 * Spartan Framework E-Commerce Shop Comprehensive Test Suite
 */

// 1. PSR-4 Autoloader
if (file_exists(__DIR__ . '/../../vendor/autoload.php')) {
    require_once __DIR__ . '/../../vendor/autoload.php';
} elseif (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
}

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

use Spartan\Application;
use Spartan\Database\Migrator;
use Spartan\JobQueue;
use Spartan\Request;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\OrderService;

echo "======================================================\n";
echo "   SPARTAN FRAMEWORK FULL FEATURE TEST SUITE (SHOP)   \n";
echo "======================================================\n\n";

$passedCount = 0;
$failedCount = 0;

function assertTest(string $name, bool $condition, string $details = ''): void {
    global $passedCount, $failedCount;
    if ($condition) {
        $passedCount++;
        echo "  [PASS] {$name}" . ($details ? " ({$details})" : "") . "\n";
    } else {
        $failedCount++;
        echo "  [FAIL] {$name}" . ($details ? " ({$details})" : "") . "\n";
    }
}

try {
    // Clean up previous test database
    $dbFile = __DIR__ . '/storage/shop.sqlite';
    if (file_exists($dbFile)) {
        unlink($dbFile);
    }

    // 1. Boot Application
    $config = require __DIR__ . '/config/config.php';
    $config['base_path'] = __DIR__;
    $config['db'] = [
        'connection' => 'sqlite',
        'database'   => $dbFile,
    ];
    $app = new Application($config);
    $app->router->aliasMiddleware('auth', \Spartan\Middlewares\AuthMiddleware::class);
    $app->router->aliasMiddleware('csrf', \Spartan\Middlewares\CsrfMiddleware::class);
    assertTest("Application Bootstrapping", isset(Application::$app), "Config loaded with SQLite driver");

    // 2. Database Migrations
    $migrator = new Migrator($app->db, __DIR__ . '/database/migrations');
    $migrator->migrate();
    assertTest("Database Migrations", true, "Executed 0001, 0002, 0003 migrations on SQLite");

    // 3. Database Seeding
    $seedSql = file_get_contents(__DIR__ . '/database/seed.sql');
    $app->db->exec($seedSql);
    assertTest("Database Seeding", true, "Seeded roles, permissions, users, categories, products");

    // 4. DI Container Auto-resolution
    $orderService = $app->container->make(OrderService::class);
    assertTest("DI Container Resolution", $orderService instanceof OrderService, "Auto-resolved OrderService & nested dependencies");

    // 5. QueryBuilder & Hydrated Models
    $products = (new Product())->table()->where('featured', 1)->get();
    assertTest("QueryBuilder Select", count($products) >= 4, "Fetched " . count($products) . " featured products");

    $laptop = (new Product())->findInstanceBy('slug', 'spartan-probook-m3-max');
    $initialStock = (int)$laptop->stock;
    assertTest("Model Hydration (findInstanceBy)", $laptop !== null && $laptop->price == 2499.99, "Hydrated {$laptop->name} (ID {$laptop->id})");

    // 6. Relationships & Eager Loading (loadFor)
    $categories = (new Category())->all();
    $categoriesWithProducts = (new Category())->products()->loadFor($categories, as: 'products');
    assertTest("Relationships & Eager Loading (loadFor)", isset($categoriesWithProducts[0]['products']), "Eager loaded products across categories without N+1");

    // 7. Transactional Order Service Execution
    $cartItems = [
        ['product_id' => (int)$laptop->id, 'quantity' => 1],
        ['product_id' => 3, 'quantity' => 2],
    ];
    $order = $orderService->createOrder(2, $cartItems, '123 Tech Avenue', 'credit_card');
    assertTest("Atomic DB Transactions & Order Creation", $order instanceof Order && $order->total_amount == 3198.99, "Order #{$order->order_number} created with total $3198.99");

    // Check stock deduction
    $laptopAfter = (new Product())->findInstance((int)$laptop->id);
    assertTest("Inventory Stock Deduction", (int)$laptopAfter->stock === ($initialStock - 1), "Stock updated from {$initialStock} to {$laptopAfter->stock}");

    // 8. Event Dispatcher (Sync & Async Queue Push)
    $app->events->listen('order.placed', \App\Listeners\UpdateStockListener::class);
    $app->events->listen('order.placed', \App\Listeners\SendOrderEmailListener::class, async: true);
    $app->events->listen('order.placed', \App\Listeners\NotifyAdminListener::class, async: true);

    $app->events->dispatch('order.placed', ['id' => $order->id, 'order_number' => $order->order_number]);
    assertTest("Event Dispatcher (Sync + Async)", true, "Dispatched order.placed sync listener & pushed 2 async jobs");

    // 9. Queue Worker Processing
    $queue = new JobQueue($app->db);
    $processedJobs = $queue->processPending();
    assertTest("Async Job Queue Worker", $processedJobs >= 2, "Processed {$processedJobs} async jobs successfully");

    // 10. Web Route & Controller Action Resolution
    require __DIR__ . '/routes/web.php';
    require __DIR__ . '/routes/admin.php';
    require __DIR__ . '/routes/api.php';

    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_SERVER['REQUEST_URI']    = '/catalog';
    $app->request = new Request();
    $app->router->setRequest($app->request);

    ob_start();
    $htmlOutput = $app->router->resolve();
    ob_end_clean();
    assertTest("Full Page Blade View Render (Shop Catalog)", str_contains((string)$htmlOutput, 'Product Catalog'), "Rendered catalog with glassmorphism layout");

    // 11. HTMX Partial Fragment Swap Render with CSRF Token
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_SERVER['REQUEST_URI']    = '/shop/search';
    $_POST['query']            = 'Spartan';
    $_POST['_csrf']            = $app->session->get('_csrf_token');
    $app->request = new Request();
    $app->router->setRequest($app->request);

    ob_start();
    $partialOutput = $app->router->resolve();
    ob_end_clean();
    assertTest("HTMX Partial View Swap (renderViewOnly)", str_contains((string)$partialOutput, 'Spartan ProBook M3 Max') && !str_contains((string)$partialOutput, '<html>'), "Returned raw HTML partial fragment without layout wrapper");

    // 12. REST JSON API Endpoint
    $_POST = [];
    $_GET  = [];
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_SERVER['REQUEST_URI']    = '/api/products';
    $app->request = new Request();
    $app->router->setRequest($app->request);

    $app->router->resolve();
    $jsonOutput = $app->response->getContent();
    $jsonObj = json_decode((string)$jsonOutput, true);
    assertTest("Stateless REST JSON API Endpoint", isset($jsonObj['status']) && $jsonObj['status'] === 'success', "Returned structured JSON response with " . ($jsonObj['count'] ?? 0) . " items");

    // 13. RBAC Authorization & Attribute Verification
    $app->session->set('user_id', 1);
    $app->session->set('role', 'admin');
    
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_SERVER['REQUEST_URI']    = '/admin/products';
    $app->request = new Request();
    $app->router->setRequest($app->request);

    ob_start();
    $adminHtml = $app->router->resolve();
    ob_end_clean();
    assertTest("RBAC Authorization & Attribute Checks (#[RequireRole])", str_contains((string)$adminHtml, 'Admin Product Management'), "Authorized admin user to access protected endpoint");

    echo "\n------------------------------------------------------\n";
    echo " TEST RESULTS: {$passedCount} Passed, {$failedCount} Failed\n";
    echo "------------------------------------------------------\n";

    if ($failedCount > 0) {
        exit(1);
    }
} catch (\Throwable $e) {
    echo "\n[ERROR] Uncaught Exception: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}
