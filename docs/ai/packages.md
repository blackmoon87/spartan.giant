# Third-party packages — when and how to use Composer libraries

"Zero dependencies" describes the **framework core** (`framework/src/`). An
application built on Spartan may add Composer packages, following this guide.

## 1. Decide: built-in first

Spartan already provides these — use them, not a package:

| Need | Built-in |
|---|---|
| database, ORM, migrations | `Spartan\Model`, `QueryBuilder`, `php spartan migrate` |
| routing, middleware | `Spartan\Router`, `Spartan\Middleware` |
| templates | Spartan Blade (`src/Views/*.blade.php`) |
| validation | `Spartan\Validator`, `FormRequest` |
| auth, roles, permissions | sessions + `HasAuthorization` + `Gate` (see `auth.md`) |
| cache, queue, events, logging | `Cache`, `JobQueue`, `EventDispatcher`, `Logger` |
| dependency injection | `Spartan\Container` |
| dates | PHP `DateTimeImmutable` |
| HTTP calls (simple) | PHP `curl_*` or `file_get_contents` with a stream context |

Packages fit what Spartan does not do:

| Need | Common package |
|---|---|
| sending email (SMTP, SES, Mailgun…) | `symfony/mailer` |
| HTTP API client (retries, async) | `guzzlehttp/guzzle` or `symfony/http-client` |
| PDF generation | `dompdf/dompdf` |
| image resize/crop | `intervention/image` |
| payments | the provider's SDK, e.g. `stripe/stripe-php` |
| spreadsheets | `phpoffice/phpspreadsheet` |
| OAuth / social login | `league/oauth2-client` |
| Markdown | `league/commonmark` |

Keep Spartan's database layer, router and views: replacing them with Eloquent,
Doctrine ORM, Symfony routing or Twig splits the app into two frameworks.

## 2. Ask, then install

Adding a dependency is the user's decision. Before installing, tell the user the
package name, what it is for, and why the built-in option is not enough — then
wait for approval. After approval:

```bash
composer require symfony/mailer            # runtime package
composer require --dev some/dev-tool       # dev tooling only
```

Commit both `composer.json` and `composer.lock`.

## 3. Wrap it in a service

Application code talks to **your** class, never to the library directly. The
library can then be swapped or mocked in one place.

1. Settings in `.env` and `config/config.php`.
2. A service class in `src/Services/` that wraps the library.
3. A container binding in `public/index.php`.
4. Controllers and listeners receive the service by injection.

### Example: email with `symfony/mailer`

`.env`:

```ini
MAIL_DSN=smtp://user:pass@smtp.example.com:587
MAIL_FROM=no-reply@example.com
```

`config/config.php` (add a key to the returned array):

```php
'mail' => [
    'dsn'  => $_ENV['MAIL_DSN'] ?? 'null://null',     // null:// discards mail (development)
    'from' => $_ENV['MAIL_FROM'] ?? 'no-reply@localhost',
],
```

`src/Services/Mailer.php`:

```php
<?php

declare(strict_types=1);

namespace App\Services;

use Symfony\Component\Mailer\Mailer as SymfonyMailer;
use Symfony\Component\Mailer\Transport;
use Symfony\Component\Mime\Email;

class Mailer
{
    private SymfonyMailer $mailer;

    public function __construct(private string $from, string $dsn)
    {
        $this->mailer = new SymfonyMailer(Transport::fromDsn($dsn));
    }

    public function send(string $to, string $subject, string $html): void
    {
        $this->mailer->send((new Email())->from($this->from)->to($to)->subject($subject)->html($html));
    }
}
```

`public/index.php` (after `$app = new Application($config);`):

```php
$app->container->singleton(\App\Services\Mailer::class,
    fn() => new \App\Services\Mailer(config('mail.from'), config('mail.dsn')));
```

Use it — render the body with a view so templates stay in `src/Views/`:

```php
public function __construct(private \App\Services\Mailer $mailer) { parent::__construct(); }

// in an action:
$this->mailer->send($email, 'Welcome!', $this->renderViewOnly('emails.welcome', ['name' => $name]));
```

Send slow work (email, API calls) from an **async event listener** so the page
responds immediately (see `services.md` → Events). Listeners are created with
`new`, so resolve the service inside `handle()`:
`\Spartan\Application::$app->container->make(\App\Services\Mailer::class)`.

### Example: HTTP API client with `guzzlehttp/guzzle`

```php
<?php

declare(strict_types=1);

namespace App\Services;

use GuzzleHttp\Client;

class WeatherApi
{
    private Client $http;

    public function __construct(string $baseUri, private string $apiKey)
    {
        $this->http = new Client(['base_uri' => $baseUri, 'timeout' => 5]);
    }

    public function forecast(string $city): array
    {
        $response = $this->http->request('GET', '/forecast', [
            'query'   => ['city' => $city],
            'headers' => ['Authorization' => 'Bearer ' . $this->apiKey],
        ]);
        return json_decode((string) $response->getBody(), true) ?? [];
    }
}
```

Bind it the same way (`config('services.weather.url')`, `config('services.weather.key')`),
and cache responses with `Cache::remember()` to stay within API limits.

## Rules for third-party code

- Follow the library's own documentation for its API; the examples above match
  `symfony/mailer` 7 and `guzzlehttp/guzzle` 7.
- Secrets (API keys, passwords, DSNs) live in `.env` only, read through
  `config/config.php` — never in code or views.
- Catch the library's exceptions inside your service and turn them into a
  result your app understands (return value or your own exception); log the
  details with `Application::$app->logger`.
- Production installs use `composer install --no-dev --optimize-autoloader`.
