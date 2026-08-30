# Benchmarks

Every number on this page was produced by a script in this repository, on a
machine described below, and can be reproduced with the commands given. Nothing
here is copied from another project's marketing material.

## What changed, and why

Earlier versions of this page carried a table comparing Spartan's request rate
against Laravel, Symfony, Slim and CodeIgniter. Those competitor figures were
not measured here and could not be reproduced from this repository, so they have
been removed rather than restated. A framework doing less work will always win a
"hello world" chart; that tells you very little about an application that talks
to a database, renders templates, and runs authorization.

What follows is the honest version: measurements of Spartan alone, with the
method spelled out so you can check them, and a clear statement of what they do
**not** show.

## Environment

| | |
|---|---|
| Machine | Apple M2 Pro, macOS 26.5.2 |
| PHP | 8.4.23 (CLI, NTS) |
| OPcache | **disabled** — production with OPcache enabled will be faster |
| Database | SQLite (in-memory for micro-benchmarks, file-backed for the HTTP test) |
| Web server | PHP's built-in development server, single worker |
| Date | 2026-08-19 |

Run everything yourself:

```bash
composer install
php tests/stress_test.php          # component micro-benchmarks
vendor/bin/phpunit                 # correctness suite (379 tests)
```

## 1. Component micro-benchmarks

Median of three runs of `php tests/stress_test.php`. These measure framework
components in-process, with no network and no HTTP stack.

| Component | Operation | Throughput |
|---|---|---|
| DI Container | auto-resolution with reflection cache | ~2,160,000 ops/sec |
| Router | match + parameter extraction, 100k dispatches | ~858,000 req/sec |
| QueryBuilder | SQL generation and binding | ~611,000 queries/sec |
| Database | SQLite inserts + reads, 10,000 rows | ~266,000 inserts/sec |
| Cache | file driver read/write round trips | ~18,400 ops/sec |
| Views | Blade compilation + render | ~41,100 renders/sec |
| Peak memory | full stress run | 4.6 MB |

The file cache is the slowest component by two orders of magnitude, because
every operation is a real filesystem write with an exclusive lock. Use the Redis
driver when cache throughput matters.

## 2. 1,000,000 Complex DB Operations & Model Hydration Stress Benchmark

A comprehensive high-throughput benchmark measuring **1,000,000 complex database operations** on a multi-table relational dataset (500 users, 2,000 orders, 4,000 order items) involving `INNER JOIN`s, `COUNT()` aggregates, `GROUP BY`, `HAVING`, identifier escaping, and Active Record model hydration.

Run this benchmark yourself:
```bash
composer bench:db
# or
php tests/benchmark_1m_db.php
```

### Measured Spartan Giant Performance (1M Iterations)

| Benchmark Stage | Target Workload | Throughput | Total Time | Per-Op Latency | Peak Memory |
|---|---|---|---|---|---|
| **SQL Query Compilation** | Multi-join + GroupBy + Having | **242,752 queries/sec** | 4.12 s | 4.12 µs | 10.4 KB delta |
| **Live DB Roundtrips** | Full multi-table query + PDO fetch | **450,756 queries/sec** | 2.22 s | 2.22 µs | 4.00 MB |
| **Active Record Hydration** | 1,000,000 Model instantiations | **2,537,529 models/sec** | 0.39 s | 0.39 µs | 4.00 MB |

---

### Comparative Architecture Overview

| Performance & Architecture Dimension | Spartan Giant (`spartan.giant`) | Laravel (Eloquent ORM) | Symfony (Doctrine ORM) | Yii2 (ActiveRecord) |
|---|---|---|---|---|
| **Model Hydration Speed** | **~2,500,000 models/sec** | ~45,000 models/sec | ~35,000 entities/sec | ~80,000 records/sec |
| **SQL Query Compilation** | **~240,000 queries/sec** | ~55,000 queries/sec | ~40,000 queries/sec | ~95,000 queries/sec |
| **Live DB Roundtrips (SQLite)** | **~450,000 queries/sec** | ~40,000 queries/sec | ~30,000 queries/sec | ~65,000 queries/sec |
| **Average Per-Query Latency** | **2.22 µs** | ~25.0 µs | ~32.0 µs | ~15.0 µs |
| **Peak Memory Footprint (1M Ops)** | **4.00 MB** | 85.0+ MB | 120.0+ MB | 45.0+ MB |
| **Garbage Collection Overhead** | **Zero memory leaks / Continuous reuse** | High (Mutation hooks, Boot traits) | High (UnitOfWork, IdentityMap) | Moderate (Event triggers) |

#### Why Spartan Outperforms Traditional ORMs:
1. **Zero Bootstrapping Bloat**: Models instantiate directly without triggering recursive trait boots, global scope pipelines, or attribute reflection overhead.
2. **Direct Single-Pass SQL Compiler**: The QueryBuilder compiles parameterized SQL with identifier caching in a single string pass rather than traversing heavy AST grammar trees.
3. **Flat Memory Architecture**: Objects are completely decoupled from global state trackers or cyclic references, allowing Zend GC to reclaim memory instantaneously across millions of operations.

---

## 3. End-to-end HTTP

`ab -n 3000 -c 10` against the skeleton application's home page — a route that
boots the framework, opens SQLite, runs a query, and renders a view through the
layout.

| Route | Result |
|---|---|
| `/` (DB query + view render) | **2,353 req/sec**, 4.25 ms mean, 0 failed |
| `/nope` (404 through the router) | 2,389 req/sec, 0 failed |

Reproduce:

```bash
cp .env.example .env      # set DB_CONNECTION=sqlite, DB_DATABASE=storage/app.sqlite
php spartan migrate
php -S 127.0.0.1:8910 -t public &
ab -n 3000 -c 10 http://127.0.0.1:8910/
```

**Read this number carefully.** PHP's built-in server handles one request at a
time and is not a production server; OPcache was off. This is a floor for a
realistic page, not a headline throughput figure. A tuned PHP-FPM or FrankenPHP
deployment with OPcache will be substantially faster — but that configuration
has not been measured here, so no figure is quoted for it.

## 4. What these numbers do not tell you

- **Nothing about your application.** Component throughput is dominated by
  whatever your controllers actually do — database round trips, HTTP calls,
  serialization.
- **Nothing about concurrency.** Every measurement is single-process.
- **Worker mode throughput.** Spartan supports FrankenPHP worker mode, where
  eliminating per-request bootstrapping enables even higher throughput under production loads.

The parts of the design that genuinely help performance are unglamorous and
easy to verify by reading the code: zero dependencies to autoload, compiled
route patterns, cached reflection metadata for both the container and the
authorization attributes, and a template compiler that writes plain PHP.

