# Services — container, events, queue, cache, logging, uploads, config, CLI

Source: `framework/src/Container.php`, `EventDispatcher.php`, `JobQueue.php`,
`Cache.php`, `TaggedCache.php`, `Logger.php`, `UploadedFile.php`, `Config.php`,
`helpers.php`, `Paths.php`, the `spartan` CLI script.

## Services and the container

A service is a plain class in `src/Services/`. Constructor dependencies with a
class type are created automatically:

```php
<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Post;
use App\Models\User;

class PostService
{
    public function __construct(private Post $posts, private User $users) {}

    public function publish(int $userId, array $input): string|false
    {
        return $this->posts->create([
            'user_id' => $userId,
            'title'   => $input['title'],
            'body'    => $input['body'],
        ]);
    }
}
```

Get it in a controller by constructor injection (call `parent::__construct()`)
or `$this->make(PostService::class)`.

Bind interfaces, scalars and shared instances in `public/index.php`:

```php
$app->container->singleton(PaymentGateway::class, fn() => new StripeGateway(env('STRIPE_KEY')));
$app->container->bind(Mailer::class, fn() => new SmtpMailer(config('mail')));   // new instance each make()
$app->container->instance('app.version', '1.4.0');
```

API: `bind($id, $factory)`, `singleton($id, $factory)`, `instance($id, $value)`,
`make($id)`, `has($id)`, `forget($id)`. A constructor parameter without a class
type needs a default value or a binding.

## Events

```php
// public/index.php
$app->events->listen('post.published', \App\Listeners\NotifyFollowers::class);              // runs now
$app->events->listen('post.published', \App\Listeners\SendDigest::class,
    async: true, maxAttempts: 3, onFailure: 'retry');                                        // queued

// in a controller
$this->event('post.published', ['post_id' => (int) $id]);
```

Listener:

```php
<?php

declare(strict_types=1);

namespace App\Listeners;

class NotifyFollowers
{
    public function handle(mixed $payload): void
    {
        // $payload is exactly what was dispatched
    }
}
```

- Listeners are created with `new` (no constructor arguments): build their
  collaborators inside `handle()`.
- Sync listeners may also be closures; async listeners must be class names.
- Async payloads must be JSON-encodable (arrays and scalars); the listener
  receives them decoded as arrays. Pass ids, not objects.

## Queue (async listeners)

Async listeners are stored in the database `jobs` table (migration `0001`) and
run by a worker:

```bash
php spartan worker            # process due jobs once (cron: * * * * * php /path/spartan worker)
php spartan worker --loop     # daemon, polls every 5 s
```

- Failed jobs retry after 0 s, 60 s, then every 300 s, up to `maxAttempts`;
  `onFailure: 'stop'` fails on the first error.
- Delivery is at-least-once: make listeners idempotent (safe to run twice).
- `QUEUE_STALE_AFTER` (default 600 s) is when a claimed job counts as abandoned;
  set it above your longest job's runtime.
- Async events always use the database queue (`QUEUE_DRIVER` in `.env` is not
  read). A Redis queue is available manually:
  `new \Spartan\JobQueue(\Spartan\QueueDrivers\RedisQueueDriver::fromConfig(config('cache')))`.

## Cache

```php
use Spartan\Cache;

Cache::put('stats', $data, 600);                  // seconds; 0 = forever
$stats = Cache::get('stats', []);                 // default when missing/expired
Cache::has('stats');  Cache::forget('stats');  Cache::flush();
$top = Cache::remember('top_posts', 300, fn() => (new Post())->published(10));
[$hits, $resetAt] = Cache::increment('signup:' . $ip, 3600);

Cache::tags(['posts'])->put('home', $html, 600);
Cache::tags(['posts'])->flush();                  // clears every key stored with that tag
```

Driver: `CACHE_DRIVER=file` (default, `storage/cache`) or `redis` (`REDIS_HOST`,
`REDIS_PORT`, `REDIS_PASSWORD`, `REDIS_DB`). Values are PHP-serialized.

## Logging

```php
Application::$app->logger->info('User {id} logged in', ['id' => $userId]);
Application::$app->logger->error('Payment failed', ['order' => $orderId]);
$payments = Application::$app->logger->channel('payments');   // storage/logs/payments-YYYY-MM-DD.log
```

Levels: `debug info notice warning error critical alert emergency`. Files:
`storage/logs/app-YYYY-MM-DD.log`, rotated at 10 MB. The `LOG_*` values in
`.env` are not applied automatically — configure in `public/index.php`:

```php
$app->logger->setMinLevel('warning');
$app->logger = new \Spartan\Logger(null, 'json');   // JSON lines instead of text
```

## File uploads

```php
$file = $this->request->upload('avatar');          // null when no file was sent
if ($file === null || !$file->isValid()) { … }
if ($file->getSize() > 2 * 1024 * 1024) { … }      // bytes
```

Careful: `getMimeType()` and `getExtension()` come from the browser — check the
real type with `finfo` on the stored file before trusting it:

```php
$path = $file->store(config('storage.uploads'), $file->hashName());   // returns path or false
$real = (new \finfo(FILEINFO_MIME_TYPE))->file($path);
if (!in_array($real, ['image/jpeg', 'image/png', 'image/webp'], true)) { unlink($path); … }
```

- `config('storage.uploads')` defaults to `storage/uploads` — outside the web
  root. Serve such files through a controller that checks access, e.g.
  `header('Content-Type: ' . $real); readfile($path);`.
- `hashName()` gives a random name and turns executable extensions (`.php`,
  `.phtml`, …) into `.bin`. `sanitizedName()` keeps the original name, cleaned.
- Methods: `isValid()`, `getOriginalName()`, `getExtension()`, `getMimeType()`,
  `getSize()`, `getError()`, `hashName()`, `sanitizedName()`, `store($dir, $name = null)`.

## Configuration

- Settings live in `config/config.php` as an array built from `.env`.
- Read with `config('app.name')`, `config('db.database')`; raw env with
  `env('KEY', $default)` (`'true'`/`'false'`/`'null'` become real values).
- Add a setting: add `'mail' => ['host' => $_ENV['MAIL_HOST'] ?? 'localhost']`
  to `config/config.php`, then read `config('mail.host')`.
- Paths: `\Spartan\Paths::base('x')`, `\Spartan\Paths::storage('x')`.

## CLI — `php spartan <command>`

| Command | Does |
|---|---|
| `migrate` · `migrate:status` · `migrate:rollback [N]` · `migrate:reset` · `migrate:fresh` | migrations (see database guide) |
| `db:seed` | run `database/seed.sql` |
| `worker` · `worker --loop` | process queued jobs |
| `config:cache` · `config:clear` | cache the config array |
| `view:clear` | delete compiled views |
| `route:list` | print every route |
| `health` | JSON health report (DB, cache, storage) |
| `lang:list` · `lang:check` | locales / missing translation keys |
