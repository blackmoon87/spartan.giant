# Changelog

All notable changes to this project are documented here.
The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- AI assistant guide: `AGENTS.md` (canonical, auto-loaded by Codex, Cursor,
  Copilot and other agents), `CLAUDE.md` (imports it), `.github/copilot-instructions.md`,
  and verified topic guides in `docs/ai/` — database, HTTP, views, auth
  (login / registration / logout / roles / gates), validation, services.
  `tests/Unit/AgentDocsTest.php` fails if the docs name a framework method that
  does not exist, and runs the documented recipes.
- AI guides for JSON APIs (token auth, CORS, JSON errors), third-party Composer
  packages (policy: built-in first, user approval, wrap in a service), testing,
  and deployment (production checklist, Nginx/Apache, cron, custom CLI
  commands, FrankenPHP worker mode).
- `errors()` / `errors('field')` helper for validation messages in views
  (`@flash()` is for strings and failed on the error array).
- `src/Views/error_500.php` in the skeleton (the 500 page rendered
  "View [error_500] not found.").
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

- **`composer install --no-dev` broke the skeleton:** `App\` is mapped only under
  `autoload-dev`, so production installs could not load any controller.
  `public/index.php`, `spartan` and `worker.php` now always register the
  `App\` namespace (and the framework when `vendor/` is absent — `worker.php`
  previously failed without `vendor/`).
- **Worker mode resumed the previous request's session.** PHP keeps the session
  ID after `session_write_close()`, so a request without a cookie inherited the
  last user's session. `Session::start()` now re-reads the current cookie.
- **The skeleton had no CSRF protection.** `public/index.php` now registers
  `CsrfMiddleware` globally; the contact form carries the token.
- **Open redirect.** `Response::redirect()` accepted `//evil.com`, `/\evil.com`,
  `https://app.com.evil.com` and `https://app.com@evil.com`. Absolute targets
  must now match the app URL's scheme, host and port exactly.
- **File cache returned expired or overwritten values** through the has()/get()
  memo. The memo is gone; `Cache::remember()` now does a single read itself
  (also one Redis round-trip instead of two), and a reader no longer deletes an
  entry it catches mid-write.
- **Forged client IPs behind a proxy.** `getIp()` trusted the left-most
  `X-Forwarded-For` entry; it now walks right-to-left past trusted proxies.
  `isSecure()` only honours `X-Forwarded-Proto` from a trusted proxy.
- **Column whitelist accepted any function** (`?sort=SLEEP(5)`, `STRCMP(password,'m')`)
  and unbalanced quotes. Functions now come from a fixed list with strict arguments.
- **`FormRequest` called `exit`**, killing worker processes, and a non-AJAX
  authorization failure was sent as `200 OK`. Failures now throw
  `HttpResponseException`, which `Application::run()` sends. Password fields are
  no longer flashed to the session.
- `Router::redirect()` wrote to a stale Response in worker mode.
- `old()` never saw input flashed by `FormRequest` (`old_input` vs `_old_input`).
- Blade compiled directives inside email addresses (`info@authority.com` →
  fatal `ParseError`). Added `@@` and `@{{ }}` escapes. Output buffers are
  released when a template throws. Compiled views are keyed by compiler version.
- Worker mode kept the first request's locale for every later request.
- `Model::create()` / `save()` crashed with a custom `AuthInterface`; `save()`
  no longer loads the current user from the database to compare ids.
- Redis queue ignored retry backoff (retries now wait in a delayed set). Both
  queue drivers count a reclaim as an attempt (a job that kills its worker no
  longer loops forever), and long batches refresh their claim so they are not
  run twice. `on_failure` values now fit the jobs table's ENUM; `run_at` is
  stamped by PHP, avoiding DB/PHP timezone drift.
- `RedisCacheDriver::flush()` ran `FLUSHDB`, wiping queues sharing the database.
  Cache keys now live under `spartan:cache:`; the rate-limit counter is atomic
  and can no longer lose its TTL.
- `ExceptionHandler` sent headers/body prepared before the failure with the 500.
- `UploadedFile::hashName()` kept executable extensions (`shell.php`).
- JSON log entries with invalid UTF-8 were silently dropped.
- `select("COALESCE(a, 'b') as c")` was split on the inner comma.
- Route literal segments were regex (`/files/{name}.json` matched `/files/aXjson`).
- `public/index.php` read `APP_DEBUG` before `.env` was loaded.
- **Inline `@section('name', $value)` compiled to a `ParseError`** (unescaped
  quote in the generated code), crashing any template using it — this was the
  long-standing taskforge test failure.
- `first()`, `paginate()` and `pluck()` left LIMIT/OFFSET/SELECT behind on the
  builder; it is now reusable after them.
- Migrations no longer split on `;` inside strings, comments or
  `CREATE TRIGGER ... END` bodies. On SQLite each file runs in a transaction
  (a failure leaves nothing applied and nothing recorded); on MySQL, where DDL
  commits implicitly, the failing statement is reported. `_down.sql` lookup
  no longer breaks on names containing `.sql`.
- Redis queue: claiming a job is one atomic Lua call (a worker dying between
  `RPOP` and `HSET` lost the job) — and ~70% faster.
- An empty `UPLOAD_PATH=` (as shipped in `.env.example`) resolved to an empty path.

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

### Removed

- The `shop` and `css_showcase` examples (and `shop-stack-demo.html`): they
  duplicated what `blogger` and `taskforge` already cover. Their screenshots
  were removed with them.

### Changed

- Example demo databases are generated, not committed: each example builds its
  SQLite database from its migrations and seed on the first request. The
  `blogger` example now defaults to SQLite, so it runs with no configuration.

- **Middleware is a real onion.** The route runs inside the pipeline, so code
  after `$next()` runs after the route and receives its result. A middleware
  that returns without calling `$next()` now stops the route; a rejecting
  guard stops inner middlewares. Resolved middleware stacks are memoised —
  dispatch through middleware is ~9% faster.
- **Validator:** an unknown rule now throws instead of silently passing. Rules
  may be given as an array (needed for `regex:` patterns containing `|`).
  `unique:table,column,ignoreId[,idColumn]` ignores the record being updated.
- **Read/write splitting is used by the ORM.** With `DB_READ_WRITE_SPLIT=true`,
  `Model::table()` sends SELECTs to a replica; after any write in the request,
  and inside transactions, reads stay on the primary (read-your-writes).
  Replicas may now be SQLite files. Disabled: no overhead.
- `VIEW_CACHE_ENABLED=true` now means production mode: compiled views are reused
  without checking template timestamps (run `php spartan view:clear` after
  deploying templates). `false` recompiles changed templates as before.
- The skeleton's default upload directory is `storage/uploads` (outside the
  web root) instead of `public/uploads`.
- `QUEUE_STALE_AFTER` (default 600) sets when a claimed job counts as abandoned;
  `new JobQueue($pdo, $staleAfter)`. Delivery is at-least-once — keep jobs
  idempotent and set this above your longest job's runtime.
- Route authorization attributes resolve the user via `Gate::resolveUser()`
  (honours `auth.model`) instead of a hard-coded `App\Models\User`.

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
