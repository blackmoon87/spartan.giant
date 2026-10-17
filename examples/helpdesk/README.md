# Spartan Helpdesk — example application

A complete support-ticket system built with the Spartan framework. Customers
open tickets, agents answer and manage them, and admins manage the team. It
runs with **zero setup**: the SQLite database builds itself, demo data
included, on the first request.

```bash
cd examples/helpdesk
php -S localhost:8090 -t public        # open http://localhost:8090
php spartan worker --loop              # second terminal: delivers notifications
```

| Account | Role | Password |
|---|---|---|
| `admin@helpdesk.test` | Administrator | `password` |
| `agent@helpdesk.test` | Support agent | `password` |
| `maria@helpdesk.test` | Customer | `password` |

To start over, delete `database/helpdesk.sqlite`.

## What it demonstrates

| Feature | Where |
|---|---|
| Registration, login, logout; `session->regenerate()`; `password_hash`/`password_verify` with rehash | `src/Controllers/AuthController.php` |
| Rate limiting (`throttle:5,60` on login and register, limits on the API) | `routes/web.php`, `routes/api.php` |
| Roles and permissions (`admin`, `agent`, `customer`; `view_all_tickets`, `manage_tickets`, `manage_users`) | `database/seed.sql`, `HasAuthorization` on `src/Models/User.php` |
| `#[RequireRole('admin')]` on a whole controller | `src/Controllers/AdminController.php` |
| A policy: who may view, reply to and manage a ticket; `@can` / `@cannot` / `@role` in views | `src/Policies/TicketPolicy.php`, `src/Views/tickets/show.blade.php` |
| FormRequests for every form and API body (redirect with `errors()`/`old()`, or 422 JSON) | `src/Controllers/Requests/` |
| Query builder: filters, grouped `OR` search, allowlisted sort, `paginate()`, `GROUP BY` counts | `src/Models/Ticket.php` |
| Relations with eager loading (`loadFor`, no N+1) | `Ticket::search()`, `Reply::forTicket()` |
| Transactions (ticket + attachment), with the stored file removed on rollback | `src/Services/TicketService.php` |
| Uploads: size limit, real type from `finfo`, random name without an extension, stored outside the web root, served only after the policy check | `src/Services/AttachmentService.php`, `src/Controllers/AttachmentController.php` |
| Sync events (activity history) and async events on the queue (notifications) | `public/index.php`, `src/Listeners/` |
| An idempotent queued listener (the queue delivers at least once) | `Notification::deliverOnce()`, unique `event_key` |
| Tagged cache: dashboard counts cached 5 minutes, flushed on every ticket change | `src/Services/StatsService.php` |
| Services with constructor injection (container autowiring) | `src/Services/`, controller constructors |
| Custom middleware: a guard (`ApiTokenAuth`) and a global one that shares view data (`ShareNavData`) | `src/Middlewares/` |
| JSON API with personal bearer tokens (only a SHA-256 hash is stored), explicit response fields, JSON errors | `routes/api.php`, `src/Controllers/Api/`, `src/ApiAwareExceptionHandler.php` |
| `PATCH` / `DELETE` from HTML forms with `@method` | `tickets/show`, `account/tokens`, `admin/users` views |
| Security headers with a strict CSP (`default-src 'self'`): no inline styles or scripts anywhere | `public/index.php`, `public/css/app.css` |
| Logging channels (`auth`, `tickets`) | `storage/logs/` |
| Every user-facing string translated | `lang/en/*.php` |
| Responsive layout from 320 px up, light and dark themes | `public/css/app.css` |

## The JSON API

Create a token on the **API tokens** page, or ask the API for one:

```bash
curl -X POST http://localhost:8090/api/v1/tokens \
  -H "Content-Type: application/json" \
  -d '{"email":"maria@helpdesk.test","password":"password","name":"cli"}'
```

| Method | Path | Body | Notes |
|---|---|---|---|
| `POST` | `/api/v1/tokens` | `email`, `password`, `name` | returns `{"token": "…"}` once; 10 per minute |
| `DELETE` | `/api/v1/tokens/current` | — | revokes the token used for the request |
| `GET` | `/api/v1/tickets` | `?status=&q=&sort=&page=&per_page=` | customers get their own tickets, staff get all |
| `GET` | `/api/v1/tickets/{id}` | — | includes replies; internal notes only for staff |
| `POST` | `/api/v1/tickets` | `subject`, `body`, `priority` | `201`, or `422` with `errors` |

Send `Authorization: Bearer <token>` and `Content-Type: application/json`.

## Layout

```
config/config.php        settings (SQLite at database/helpdesk.sqlite by default)
database/migrations/     jobs, auth tables, helpdesk tables, api_tokens
database/seed.sql        demo users, roles, permissions, tickets
public/index.php         middleware, policy, events, error handler, first-run DB build
routes/web.php|api.php   every route, with its middleware
src/Controllers/         web controllers; Api/ for the JSON API; Requests/ for FormRequests
src/Models/              all SQL lives here
src/Services/            TicketService (every ticket change), AttachmentService, StatsService
src/Policies/            TicketPolicy
src/Listeners/           RecordActivity (sync), SendNotification (queued)
src/Middlewares/         ApiTokenAuth, ShareNavData
src/Views/               Blade views; layouts/app.blade.php is the shell
lang/en/                 every visible string
spartan                  CLI: migrate, worker, route:list, health, …
```

## With Composer or MySQL

`composer install` in this folder loads the framework from the repository root
through a path repository (`../../`); without it, `public/index.php` and
`spartan` load the framework directly. For MySQL, copy `.env.example` to `.env`,
set `DB_CONNECTION=mysql` and the `DB_*` values, then run `php spartan migrate`
and `php spartan db:seed`.
