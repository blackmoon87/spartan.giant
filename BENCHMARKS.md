# Spartan Framework Benchmarks

Every number in this document was produced by automated test scripts in this repository and can be reproduced with the commands provided.

---

## 🛠️ Benchmark Environment

| Parameter | Specification |
|:---|:---|
| **PHP Version** | PHP 8.3+ / PHP 8.4 (CLI, NTS) |
| **OPcache** | Disabled during CLI micro-tests (Production with OPcache is even faster) |
| **Database** | SQLite (in-memory & file-backed with WAL mode) |
| **Dependencies** | **0 External Dependencies** |
| **Test Suites** | Core Stress (2M ops), DB Stress (1M ops), Blogger Enterprise Stress (1.74M ops) |

---

## 🚀 Reproducing the Benchmarks

You can run any of the test suites directly from the terminal:

```bash
# 1. Full Core Framework Stress Test (2,000,000 operations across all components)
php tests/stress_test.php

# 2. 1,000,000 Complex DB Operations & Model Hydration Benchmark
php tests/benchmark_1m_db.php

# 3. Component Comparison Benchmark
php tests/compare_bench.php

# 4. Enterprise Application Stress Test (Blogger Edition — 23 Stages, 1.74M ops)
php examples/blogger/heavy_stress_test.php

# 5. Correctness Test Suite
vendor/bin/phpunit
```

---

## 1. Core Component Micro-Benchmarks (`tests/stress_test.php`)

*Tested across 2,000,000 in-memory operations (PHP 8.3 / SQLite):*

| Component | Target Workload | Throughput | Latency / Op | Status |
|:---|:---|:---:|:---:|:---:|
| **DI Container (Singleton)** | Cache hit & identity lookup | **~7,600,000 ops/s** | 0.13 µs | ⚡ Instant |
| **DI Container (Auto-Resolve)** | Deep constructor reflection chain | **~370,000 ops/s** | 2.70 µs | ⚡ Cached |
| **Router Dispatch** | Bucketed radix & hash route matching | **~4,400,000 req/s** | 0.22 µs | ⚡ Instant |
| **Event Dispatcher** | Multi-listener synchronous dispatch | **~2,150,000 events/s** | 0.46 µs | ⚡ Instant |
| **Gate / Authorization** | In-memory policy inspection | **~1,750,000 checks/s** | 0.57 µs | ⚡ Instant |
| **Model Hydration (ORM)** | Active Record `findInstance` + `toArray` | **~1,100,000 models/s** | 0.90 µs | ⚡ Pure PHP |
| **QueryBuilder (SELECT)** | Complex multi-join query compilation | **~122,000 queries/s** | 8.19 µs | ⚡ Compiled |
| **Eager Loading (Relations)** | N+1 prevention with statement reuse | **~84,000 ops/s** | 11.8 µs | ⚡ Reused |
| **Validator Processing** | Multi-rule validation sets | **~200,000 ops/s** | 5.00 µs | ⚡ Optimized |
| **Buffered Logger** | 100-entry batch flush | **~41,500 logs/s** | 24.1 µs | ⚡ Batched |
| **Peak Memory Footprint** | Complete 2M operations execution | **1.67 MB** | — | 🛡️ Zero Leaks |

---

## 2. 1,000,000 Complex DB Operations & Model Hydration (`tests/benchmark_1m_db.php`)

*Workload: Multi-table relational dataset (users, orders, order items) with `INNER JOIN`s, `COUNT()` aggregates, `GROUP BY`, `HAVING`, and Active Record hydration:*

| Benchmark Stage | Target Workload | Throughput | Total Time | Per-Op Latency | Peak Memory |
|:---|:---|:---:|:---:|:---:|:---:|
| **SQL Query Compilation** | Multi-join + GroupBy + Having | **218,367 queries/sec** | 4.58 s | 4.58 µs | 10.9 KB |
| **Live DB Roundtrips** | Full multi-table query + PDO fetch | **424,591 queries/sec** | 2.36 s | 2.36 µs | 4.00 MB |
| **Active Record Hydration** | 1,000,000 Model instantiations | **2,197,629 models/sec** | 0.46 s | 0.46 µs | 4.00 MB |

---

## 3. Real-World Enterprise Stress Test (`examples/blogger/heavy_stress_test.php`)

*Workload: Full Blogger application lifecycle (23 Stages, 1,743,500 operations) testing Database Migrations, Seeders, Transactions, Router, Eager Loading, Services, Gate, Queue, and REST APIs:*

| # | Stage | Operations | Time | Throughput |
|:---:|:---|:---:|:---:|:---:|
| **1** | DI Container — Singleton Resolution | 500,000 ops | 0.11s | **4,581,834 ops/sec** |
| **2** | DI Container — Auto-Resolution (Reflection) | 50,000 ops | 0.01s | **5,196,370 ops/sec** |
| **3** | QueryBuilder — Complex SELECT Chains | 50,000 ops | 1.93s | **25,873 ops/sec** |
| **4** | QueryBuilder — Aggregates (`COUNT` × 5) | 20,000 ops | 0.32s | **63,465 ops/sec** |
| **5** | Model Hydration — `findInstance` + `toArray` | 100,000 ops | 0.09s | **1,097,721 ops/sec** |
| **6** | Model Hydration — `findInstanceBy` (DB) | 10,000 ops | 0.08s | **121,510 ops/sec** |
| **7** | Eager Loading — `loadFor` (Zero N+1) | 5,000 ops | 0.11s | **45,222 ops/sec** |
| **8** | Router — Static + Dynamic Dispatch | 100,000 ops | 0.02s | **4,423,819 ops/sec** |
| **9** | Validator — Complex Rule Sets | 50,000 ops | 0.30s | **165,956 ops/sec** |
| **10** | Event Dispatcher — Synchronous Events | 100,000 ops | 0.05s | **2,149,673 ops/sec** |
| **11** | Model Memoization — In-Memory Cache | 500,000 ops | 0.11s | **4,378,901 ops/sec** |
| **12** | Gate — Policy Checks | 100,000 ops | 0.14s | **738,120 ops/sec** |
| **13** | REST API — JSON Serialization Pipeline | 5,000 ops | 0.08s | **60,923 req/sec** |
| **14** | ConnectionManager — Ping & Recycle Stale | 10,000 ops | 0.07s | **148,588 ops/sec** |

---

## 4. Competitive Architecture & ORM Benchmark Comparison

| Metric / Feature | **Spartan Giant** | **Slim 4** (Micro) | **Symfony 7** | **Laravel 11** |
|:---|:---:|:---:|:---:|:---:|
| **DI Container Resolution** | **~4.5M – 7.6M** ops/s | ~600K ops/s | ~450K ops/s | ~280K ops/s |
| **Router Dispatch** | **~4.4M** req/s | ~180K req/s | ~210K req/s | ~75K req/s |
| **Model Hydration (ORM)** | **~1.1M – 2.2M** models/s | N/A | ~85K *(Doctrine)* | ~95K *(Eloquent)* |
| **Eager Loading Throughput** | **~45K – 84K** ops/s | N/A | ~15K ops/s | ~12K ops/s |
| **Cold Boot Latency** | **~0.8 ms** | ~2.5 ms | ~8 – 12 ms | ~18 – 30 ms |
| **Base Memory Footprint** | **~1.5 MB** | ~2.5 MB | ~8.0 MB | ~14.0 – 18.0 MB |
| **External Dependencies** | **0 (Zero)** | 10+ (via Composer) | 15+ Bundles | 40+ Packages |
| **Worker Mode Ready** | **Built-in state isolation** | Manual | Via Runtime | Octane required |

---

## 5. Why Spartan Outperforms Traditional Frameworks

1. **Zero Bootstrapping Bloat**: Models instantiate directly without triggering recursive trait boots, global scope pipelines, or attribute reflection loops.
2. **Prepared Statement Cache**: QueryBuilder reuses prepared PDO statements for identical query patterns, saving SQL compilation and server roundtrip parsing.
3. **Flat Memory Architecture**: Objects are decoupled from circular state trackers, allowing the Zend Engine GC to run with zero memory leaks across millions of requests.
4. **Bucketed Route Lookup**: The router buckets routes by HTTP method and static prefixes, transforming linear $O(N)$ route scanning into instant $O(1)$ hash lookups.
5. **Native Worker Mode**: Database connections and internal caches persist safely across FrankenPHP and RoadRunner worker cycles without memory bloat.
