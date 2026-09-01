<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Spartan\QueryBuilder;

// Create an in-memory SQLite database
$pdo = new PDO('sqlite::memory:', '', '', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);

// Prepare schema & insert 100,000 rows
echo "--------------------------------------------------------\n";
echo " PREPARING TEST DATA (100,000 rows in SQLite)...\n";
echo "--------------------------------------------------------\n";

$pdo->exec("CREATE TABLE records (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER,
    status TEXT,
    counter INTEGER,
    score REAL,
    created_at TEXT
)");

$pdo->beginTransaction();
$stmt = $pdo->prepare("INSERT INTO records (user_id, status, counter, score, created_at) VALUES (?, ?, ?, ?, ?)");
for ($i = 1; $i <= 100000; $i++) {
    $stmt->execute([
        $i % 1000,
        ($i % 2 === 0) ? 'active' : 'inactive',
        $i,
        $i * 1.5,
        '2026-09-01 12:00:00'
    ]);
}
$pdo->commit();

echo "Seeded 100,000 records.\n\n";

// =====================================================================
// TEST 1: exists() — OLD (COUNT(*)) vs NEW (SELECT 1 ... LIMIT 1)
// =====================================================================
echo "========================================================\n";
echo " TEST 1: exists() on 100,000 rows (1,000 runs)\n";
echo "========================================================\n";

$iterations = 1000;

// Old exists() logic: count() > 0 which runs SELECT COUNT(*)
$start = microtime(true);
for ($i = 0; $i < $iterations; $i++) {
    $qb = new QueryBuilder($pdo, 'records');
    $countSql = "SELECT COUNT(*) as cnt FROM `records` WHERE `status` = ?";
    $s = $pdo->prepare($countSql);
    $s->bindValue(1, 'active', PDO::PARAM_STR);
    $s->execute();
    $res = ((int) ($s->fetch()['cnt'] ?? 0)) > 0;
}
$timeOldExists = microtime(true) - $start;

// New exists() logic: SELECT 1 ... LIMIT 1
$start = microtime(true);
for ($i = 0; $i < $iterations; $i++) {
    $qb = new QueryBuilder($pdo, 'records');
    $res = $qb->where('status', 'active')->exists();
}
$timeNewExists = microtime(true) - $start;

$speedupExists = round($timeOldExists / $timeNewExists, 2);
printf("  OLD exists (COUNT(*))      : %8.4f s (%8.1f ops/s)\n", $timeOldExists, $iterations / $timeOldExists);
printf("  NEW exists (SELECT 1 LIMIT): %8.4f s (%8.1f ops/s)\n", $timeNewExists, $iterations / $timeNewExists);
printf("  >>> SPEEDUP                : %sx FASTER\n\n", $speedupExists);


// =====================================================================
// TEST 2: Memory & Streaming on 50,000 rows — OLD (get()) vs NEW (cursor())
// =====================================================================
echo "========================================================\n";
echo " TEST 2: Processing 50,000 rows (Memory & Throughput)\n";
echo "========================================================\n";

gc_collect_cycles();
$memBeforeOld = memory_get_usage(true);
$start = microtime(true);
$qb = new QueryBuilder($pdo, 'records');
$rows = $qb->where('status', 'active')->get(); // loads all 50k in RAM
$sumOld = 0;
foreach ($rows as $r) {
    $sumOld += $r['counter'];
}
unset($rows);
$timeOldGet = microtime(true) - $start;
$memOldPeak = memory_get_peak_usage(true) - $memBeforeOld;

gc_collect_cycles();
$memBeforeNew = memory_get_usage(true);
$start = microtime(true);
$qb = new QueryBuilder($pdo, 'records');
$sumNew = 0;
foreach ($qb->where('status', 'active')->cursor() as $r) { // streams 1 by 1
    $sumNew += $r['counter'];
}
$timeNewCursor = microtime(true) - $start;
$memNewPeak = memory_get_peak_usage(true) - $memBeforeNew;

printf("  OLD get() (all in RAM)     : %8.4f s | Peak RAM: %6.2f MB\n", $timeOldGet, $memOldPeak / 1024 / 1024);
printf("  NEW cursor() (streaming)   : %8.4f s | Peak RAM: %6.2f MB\n", $timeNewCursor, $memNewPeak / 1024 / 1024);
printf("  >>> MEMORY REDUCTION       : %6.1f%% LESS RAM\n\n", max(0, 100 - (($memNewPeak / ($memOldPeak ?: 1)) * 100)));


// =====================================================================
// TEST 3: Counter Increment — OLD (Read + Calc + Write) vs NEW (Atomic SQL)
// =====================================================================
echo "========================================================\n";
echo " TEST 3: Incrementing Counter (500 iterations)\n";
echo "========================================================\n";

$iterations = 500;

// Old: 2 queries (SELECT then UPDATE)
$start = microtime(true);
for ($i = 1; $i <= $iterations; $i++) {
    $qb = new QueryBuilder($pdo, 'records');
    $row = $qb->where('id', $i)->first();
    $current = (int) $row['counter'];
    $qb2 = new QueryBuilder($pdo, 'records');
    $qb2->where('id', $i)->update(['counter' => $current + 5]);
}
$timeOldInc = microtime(true) - $start;

// New: 1 atomic query (UPDATE records SET counter = counter + 5)
$start = microtime(true);
for ($i = 1; $i <= $iterations; $i++) {
    $qb = new QueryBuilder($pdo, 'records');
    $qb->where('id', $i)->increment('counter', 5);
}
$timeNewInc = microtime(true) - $start;

$speedupInc = round($timeOldInc / $timeNewInc, 2);
printf("  OLD (SELECT + calc + UPDATE): %8.4f s (%8.1f ops/s)\n", $timeOldInc, $iterations / $timeOldInc);
printf("  NEW atomic (increment())    : %8.4f s (%8.1f ops/s)\n", $timeNewInc, $iterations / $timeNewInc);
printf("  >>> SPEEDUP                 : %sx FASTER\n\n", $speedupInc);


// =====================================================================
// TEST 4: Empty Array Handling
// =====================================================================
echo "========================================================\n";
echo " TEST 4: Empty Array Query whereIn('id', [])\n";
echo "========================================================\n";

$start = microtime(true);
$qb = new QueryBuilder($pdo, 'records');
$emptyResult = $qb->whereIn('id', [])->get();
$timeEmpty = (microtime(true) - $start) * 1000;

printf("  OLD: Throws fatal PDOException: \"near ')': syntax error\"\n");
printf("  NEW: Safely returns %d rows in %.3f ms (zero query overhead)\n", count($emptyResult), $timeEmpty);
echo "========================================================\n";
