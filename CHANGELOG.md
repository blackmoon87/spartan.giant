# Changelog

All notable changes to this project are documented here.
The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- Distributable package: the framework is now `spartan/framework`, autoloaded
  under the `Spartan\` namespace from `framework/src/`. The application skeleton
  keeps the `App\` namespace in `src/`.
- `Spartan\Paths` resolves the project root, so the framework no longer infers
  paths from its own location and can be installed in `vendor/`.
- PHPUnit test suite (379 tests) alongside the existing dependency-free runner.
- PHPStan static analysis at level 5, clean.
- GitHub Actions CI: PHP 8.1–8.4 matrix, lowest-dependency run, static analysis,
  coverage, and a job that boots an example application and checks its routes.
- `SECURITY.md` with a private disclosure process and application hardening notes.
- Trusted-proxy support: `TRUSTED_PROXIES` (IPs, CIDR ranges, or `*`).
- `Cache::increment()` with atomic counters (file locking, or Redis `INCR`).
- `JobQueue::reclaimStale()` returns jobs abandoned by a dead worker.
- `Session::start()` / `Session::close()` for worker-mode request lifecycles.

### Fixed

- **Worker mode leaked the authenticated user between requests.** The resolved
  identity stayed in the container, so the next request — including an anonymous
  one — could resolve the previous user.
- **SQL identifiers and operators were interpolated unchecked.** Column
  expressions and comparison operators are now validated against a whitelist,
  so a user-supplied `?sort=` parameter can no longer inject SQL.
- **Migrations produced `NULL` primary keys on SQLite.** The MySQL→SQLite
  translation missed column definitions aligned with multiple spaces, leaving a
  non-rowid `INT PRIMARY KEY` — which silently broke the job queue.
- **Query parameters bound as strings returned wrong rows.** Integers now bind
  as integers, fixing comparisons in expressions without column affinity
  (for example `HAVING` on an aggregate alias).
- **`X-Forwarded-For` was trusted unconditionally**, letting any client forge an
  IP and bypass rate limiting.
- **CSRF was only enforced on POST.** Native PUT, PATCH and DELETE are covered.
- **A rejected CSRF token produced a 500.** It now renders a 403.
- **Global middleware did not run on 404s**, so security headers and rate limits
  were absent on unmatched paths — exactly the traffic scanners generate.
- **`count()` silently dropped joins and `groupBy`**, producing wrong numbers or
  SQL errors; it now shares one builder with `paginate()`.
- **Rate-limit counters lost hits under concurrency**, letting parallel clients
  exceed the configured limit.
- **Routes with multiple closure parameters fataled** ("Unknown named
  parameter"); arguments are now always passed positionally.
- **A typo'd controller action raised a raw `ReflectionException`** instead of
  `BadMethodCallException`.
- Nested `Model::transaction()` calls no longer attempt a second `BEGIN`.
- Corrupt or truncated cache files degrade to a miss instead of a warning.
- Compiled views and the route cache are written atomically (temp file + rename).
- Compiled view cache keys include the full source path, preventing collisions
  between two view roots.
- Self-referencing middleware groups raise `LogicException` instead of looping
  forever.
- `Model::save()` respects an explicitly supplied `updated_at`, matching `create()`.

### Changed

- `examples/` no longer vendor a copy of the framework source; each example
  requires `spartan/framework` through a Composer path repository.
- Router: dynamic-route patterns are compiled once and static routes are skipped
  during pattern matching; authorization attributes are reflected once per
  class/method. Router throughput improved roughly 20% and worst-case dynamic
  matching about 3×.
- `QueryBuilder` caches compiled column expressions per dialect (~65% faster
  SQL generation).
- The README performance section now documents what the benchmark measures and
  how to reproduce it, instead of comparing against other frameworks.
