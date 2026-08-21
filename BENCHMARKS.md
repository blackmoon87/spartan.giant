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

## 2. End-to-end HTTP

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

## 3. What these numbers do not tell you

- **Nothing about other frameworks.** No comparison was run, so none is claimed.
- **Nothing about your application.** Component throughput is dominated by
  whatever your controllers actually do — database round trips, HTTP calls,
  serialization.
- **Nothing about concurrency.** Every measurement is single-process.
- **Nothing about worker mode.** Spartan supports FrankenPHP worker mode, but no
  worker-mode benchmark is published here because none has been run under
  conditions worth quoting.

The parts of the design that genuinely help performance are unglamorous and
easy to verify by reading the code: zero dependencies to autoload, compiled
route patterns, cached reflection metadata for both the container and the
authorization attributes, and a template compiler that writes plain PHP.
