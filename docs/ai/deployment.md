# Deployment and operations — production, servers, cron, CLI, worker mode

Source: `public/index.php`, `public/.htaccess`, `config/config.php`, `spartan`,
`worker.php`, `framework/src/Application.php`.

## Production checklist

1. **Code and dependencies**

   ```bash
   composer install --no-dev --optimize-autoloader
   ```

2. **`.env`** (copy from `.env.example`):

   ```ini
   APP_ENV=production
   APP_DEBUG=false                 # never true in production: it shows errors
   APP_URL=https://example.com     # used to validate absolute redirects
   DB_CONNECTION=mysql             # + DB_HOST, DB_DATABASE, DB_USERNAME, DB_PASSWORD
   VIEW_CACHE_ENABLED=true         # skip template change checks
   ROUTE_CACHE_ENABLED=true        # only when every route uses a controller, not a closure
   TRUSTED_PROXIES=10.0.0.0/8      # only behind a load balancer / reverse proxy
   CACHE_DRIVER=file               # or redis
   ```

3. **Database**: `php spartan migrate` (and `php spartan db:seed` once, if you
   seed data). Check with `php spartan migrate:status`.
4. **Permissions**: the web-server user must be able to write `storage/`
   (cache, logs, compiled views, uploads) and, for SQLite, the database file
   and its folder.
5. **After each deploy**: `php spartan view:clear` (templates changed) and
   delete `storage/cache/routes.php` (routes changed) — both rebuild on the
   next request.
6. **Health**: `php spartan health` exits non-zero when the database, cache or
   storage is failing — use it in monitoring.

`php spartan config:cache` writes `storage/cache/config.php`, but
`public/index.php` does not read that file: skip it.

## Web server

The document root is **`public/`** — never the project folder.

Apache: `public/.htaccess` (included) sends every non-file request to
`index.php`; enable `mod_rewrite` and `AllowOverride All`.

Nginx + PHP-FPM:

```nginx
server {
    listen 80;
    server_name example.com;
    root /var/www/app/public;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
    }
}
```

Local development only: `php -S localhost:8000 -t public`.

## Background jobs (async event listeners)

Run the worker continuously with a process manager, e.g. systemd:

```ini
[Service]
WorkingDirectory=/var/www/app
ExecStart=/usr/bin/php spartan worker --loop
Restart=always
```

or process due jobs once a minute from cron:

```cron
* * * * * cd /var/www/app && php spartan worker >> storage/logs/worker.log 2>&1
```

Restart the worker after each deploy so it loads the new code.

## Scheduled tasks

There is no built-in scheduler: add a CLI command (below) and run it from cron.

```cron
0 3 * * *  cd /var/www/app && php spartan report:daily >> storage/logs/cron.log 2>&1
```

## Custom CLI commands

Commands live in the `switch ($command)` of the `spartan` file. Add a `case`
above `case 'help':` (and a line to the help text):

```php
    case 'report:daily':
        $ensureDb();                                   // exits with a message when no DB
        $since = date('Y-m-d 00:00:00', strtotime('-1 day'));
        $count = (new \App\Models\Post())->table()->where('created_at', $since, '>=')->count();
        echo "Posts in the last day: {$count}\n";
        break;
```

In scope: `$app` (the Application), `$config`, `$argv` (`$argv[2]` is the first
argument), `$ensureDb()`. Use `exit(1)` for failures so cron and CI notice.

## Sessions in production

Sessions use PHP's native handler (files in `session.save_path`). Every
request starts a session, so traffic without cookies (bots, monitors) creates
session files: make sure PHP's session garbage collection runs
(`session.gc_probability` > 0) or store sessions in Redis via `php.ini`
(`session.save_handler = redis`, requires ext-redis). Behind HTTPS the cookie
is marked `Secure` automatically (with a proxy, set `TRUSTED_PROXIES`).

## FrankenPHP worker mode

`public/index.php` serves requests in a loop when `FRANKENPHP_WORKER=true` and
FrankenPHP is the server (`FRANKENPHP_MAX_REQUESTS`, default 1000, recycles the
worker). Between requests the framework resets the request, response, session,
logged-in user, model memoization, view state, translations and stale DB
connections.

Worker mode keeps PHP objects alive across requests, so in application code:

- keep per-request data (current user, request values, results) in local
  variables or the per-request objects — never in `static` properties or
  container singletons;
- register routes, gates, listeners and bindings once in `public/index.php`
  (the boot section runs once per worker);
- call `parent::__construct()` in controllers and read `$this->request` inside
  actions, not in constructors of long-lived services.
