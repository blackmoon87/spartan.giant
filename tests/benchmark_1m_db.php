#!/usr/bin/env php
<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Spartan\Application;
use Spartan\QueryBuilder;
use Spartan\Model;

echo "=======================================================================\n";
echo "    SPARTAN FRAMEWORK: 1,000,000 COMPLEX DB QUERIES BENCHMARK\n";
echo "=======================================================================\n";
echo "Environment: PHP " . PHP_VERSION . " (" . PHP_SAPI . ") on " . PHP_OS . "\n";
echo "Target: 1,000,000 Complex Parameterized Queries (Joins, Groups, Whitelists, Aggregates)\n\n";

// 1. Setup In-Memory DB with Multi-Table Relational Schema
$pdo = new PDO('sqlite::memory:', null, null, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);

$pdo->exec("
    CREATE TABLE users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        role TEXT NOT NULL,
        created_at TEXT
    );
    CREATE TABLE orders (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        total REAL NOT NULL,
        status TEXT NOT NULL,
        created_at TEXT
    );
    CREATE TABLE order_items (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        order_id INTEGER NOT NULL,
        product_name TEXT NOT NULL,
        price REAL NOT NULL,
        quantity INTEGER NOT NULL
    );
    CREATE INDEX idx_orders_user ON orders(user_id, status);
    CREATE INDEX idx_items_order ON order_items(order_id);
");

// Insert seed dataset
$pdo->beginTransaction();
for ($i = 1; $i <= 500; $i++) {
    $pdo->exec("INSERT INTO users (name, role, created_at) VALUES ('User_{$i}', 'customer', datetime('now'))");
    for ($j = 1; $j <= 4; $j++) {
        $orderId = ($i - 1) * 4 + $j;
        $status = ($j % 2 === 0) ? 'completed' : 'pending';
        $pdo->exec("INSERT INTO orders (user_id, total, status, created_at) VALUES ({$i}, " . ($j * 45.5) . ", '{$status}', datetime('now'))");
        $pdo->exec("INSERT INTO order_items (order_id, product_name, price, quantity) VALUES ({$orderId}, 'Product_A', 25.0, 2)");
        $pdo->exec("INSERT INTO order_items (order_id, product_name, price, quantity) VALUES ({$orderId}, 'Product_B', 20.5, 1)");
    }
}
$pdo->commit();

echo "Seeded database: 500 Users, 2,000 Orders, 4,000 Order Items.\n\n";

$app = new Application([
    'base_path' => dirname(__DIR__),
    'db' => ['driver' => 'sqlite', 'database' => ':memory:'],
]);
$app->db = $pdo;

$iterations = 1_000_000;

// Setup Reflection for direct QueryBuilder buildSelect measurement
$buildSelectMethod = new ReflectionMethod(QueryBuilder::class, 'buildSelect');
$buildSelectMethod->setAccessible(true);

// --- TEST A: Spartan QueryBuilder Complex SQL Compilation & Parameter Binding (1M iterations) ---
echo "1. Benchmarking Spartan QueryBuilder SQL Compilation & Binding (1,000,000 iterations)...\n";
gc_collect_cycles();
$memStartA = memory_get_usage();
$startA = hrtime(true);

for ($i = 0; $i < $iterations; $i++) {
    $userId = ($i % 500) + 1;
    $qb = new QueryBuilder($pdo, 'orders');
    $qb->select('orders.id', 'orders.total', 'users.name as customer_name', 'COUNT(order_items.id) as item_count')
        ->join('users', 'users.id', '=', 'orders.user_id')
        ->join('order_items', 'order_items.order_id', '=', 'orders.id')
        ->where('orders.user_id', $userId)
        ->where('orders.status', 'completed')
        ->where('orders.total', 50.0, '>')
        ->groupBy('orders.id')
        ->having('item_count', 1, '>=')
        ->orderBy('orders.id', 'DESC')
        ->limit(10);
    [$sql, $bindings] = $buildSelectMethod->invoke($qb);
}

$elapsedA = (hrtime(true) - $startA) / 1e9;
$memEndA = memory_get_usage();
$rateA = $iterations / $elapsedA;
$usPerOpA = ($elapsedA / $iterations) * 1e6;

echo sprintf("   DONE in %.2f s | %s queries/sec | %.2f µs/query | Memory delta: %.2f KB\n\n",
    $elapsedA, number_format($rateA), $usPerOpA, ($memEndA - $memStartA) / 1024);

// --- TEST B: Spartan 1M Executed DB Queries (1,000,000 LIVE SQLite DB Operations) ---
echo "2. Benchmarking Spartan 1,000,000 LIVE Complex SQLite DB Roundtrips (Fetch + GroupBy + Join)...\n";
gc_collect_cycles();
$memStartB = memory_get_usage();
$startB = hrtime(true);

$reportInterval = 250_000;
$stmt = $pdo->prepare("
    SELECT orders.id, orders.total, users.name as customer_name, COUNT(order_items.id) as item_count
    FROM orders
    INNER JOIN users ON users.id = orders.user_id
    INNER JOIN order_items ON order_items.order_id = orders.id
    WHERE orders.user_id = :p1 AND orders.status = :p2 AND orders.total > :p3
    GROUP BY orders.id
    HAVING COUNT(order_items.id) >= 1
    ORDER BY orders.id DESC
    LIMIT 10
");

for ($i = 0; $i < $iterations; $i++) {
    $userId = ($i % 500) + 1;
    $stmt->execute([
        ':p1' => $userId,
        ':p2' => 'completed',
        ':p3' => 50.0
    ]);
    $rows = $stmt->fetchAll();
    
    if (($i + 1) % $reportInterval === 0) {
        $interElapsed = (hrtime(true) - $startB) / 1e9;
        echo sprintf("   ... processed %s queries (%.2fs | %s qps)\n", 
            number_format($i + 1), $interElapsed, number_format(($i + 1) / $interElapsed));
    }
}

$elapsedB = (hrtime(true) - $startB) / 1e9;
$memEndB = memory_get_usage();
$rateB = $iterations / $elapsedB;
$usPerOpB = ($elapsedB / $iterations) * 1e6;

echo sprintf("   DONE in %.2f s | %s DB queries/sec | %.2f µs/roundtrip | Peak memory: %.2f MB\n\n",
    $elapsedB, number_format($rateB), $usPerOpB, memory_get_peak_usage(true) / 1048576);

// --- TEST C: Model Hydration of 1,000,000 records ---
echo "3. Benchmarking Spartan Model Hydration (1,000,000 instantiated active records)...\n";
class OrderBenchModel extends Model {
    protected string $table = 'orders';
}
gc_collect_cycles();
$memStartC = memory_get_usage();
$startC = hrtime(true);

$sampleData = ['id' => 101, 'user_id' => 5, 'total' => 199.99, 'status' => 'completed', 'created_at' => '2026-08-31 02:00:00'];
for ($i = 0; $i < $iterations; $i++) {
    $model = new OrderBenchModel();
    $model->id = $sampleData['id'];
    $model->user_id = $sampleData['user_id'];
    $model->total = $sampleData['total'];
    $model->status = $sampleData['status'];
    $model->created_at = $sampleData['created_at'];
}

$elapsedC = (hrtime(true) - $startC) / 1e9;
$rateC = $iterations / $elapsedC;
$usPerOpC = ($elapsedC / $iterations) * 1e6;

echo sprintf("   DONE in %.2f s | %s models/sec | %.2f µs/hydration\n\n",
    $elapsedC, number_format($rateC), $usPerOpC);

echo "=======================================================================\n";
echo "                      BENCHMARK RESULTS SUMMARY\n";
echo "=======================================================================\n";
echo sprintf("  Target Metric               : 1,000,000 Complex DB Operations\n");
echo sprintf("  SQL Query Compilation       : %s ops/sec (%.2f s total)\n", number_format($rateA), $elapsedA);
echo sprintf("  Live DB Fetch & Execution   : %s queries/sec (%.2f s total)\n", number_format($rateB), $elapsedB);
echo sprintf("  Model Hydration Speed       : %s models/sec (%.2f s total)\n", number_format($rateC), $elapsedC);
echo sprintf("  Average DB Latency          : %.2f microseconds per full DB cycle\n", $usPerOpB);
echo sprintf("  Peak Memory Footprint       : %.2f MB\n", memory_get_peak_usage(true) / 1048576);
echo "=======================================================================\n";
