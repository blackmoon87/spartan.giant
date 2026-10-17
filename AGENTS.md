# AGENTS.md — building on Spartan

Spartan is a small, dependency-free PHP 8.1+ MVC framework. Every API you need is
in `framework/src/` (~9k lines) — when a guide here and the code disagree, **the
code wins**: open the class and read it. Write code the Spartan way, which is
close to Laravel in shape but different in detail (see *Laravel habits* below).

## Topic guides — read the matching guide before writing that kind of code

| Before you write…                                        | Read                          |
|----------------------------------------------------------|-------------------------------|
| models, queries, relations, transactions, migrations     | `docs/ai/database.md`         |
| routes, controllers, requests, responses, middleware     | `docs/ai/http.md`             |
| Blade views, layouts, forms, flash messages, translation | `docs/ai/views.md`            |
| login, registration, logout, roles, permissions, gates   | `docs/ai/auth.md`             |
| validation rules or a FormRequest                        | `docs/ai/validation.md`       |
| services, DI container, events, queue, cache, logs, uploads, config, CLI | `docs/ai/services.md` |

Deep design and UI law (styling, responsive layouts): `important.for.coding.by.spartan.rules.md`.

## Project map — where each thing goes

```
config/config.php        all settings, read from .env (copy .env.example → .env)
routes/web.php|api.php|admin.php   route definitions ($app is in scope)
public/index.php         front controller: register global middleware, gates,
                         event listeners and container bindings here, after
                         `$app = new Application($config);`
src/Controllers/         App\Controllers\…   extend Spartan\Controller
src/Controllers/Requests/  App\Controllers\Requests\…  extend Spartan\FormRequest
src/Models/              App\Models\…        extend Spartan\Model
src/Services/            App\Services\…      plain classes, injected by the container
src/Listeners/           App\Listeners\…     classes with handle(mixed $payload)
src/Middlewares/         App\Middlewares\…   extend Spartan\Middleware
src/Policies/            App\Policies\…      plain classes, one method per ability
src/Views/               *.blade.php templates (dot or slash names: 'blog.show')
database/migrations/     NNNN_name.sql + NNNN_name_down.sql (plain SQL)
database/seed.sql        seed data for `php spartan db:seed`
lang/{locale}/{group}.php  translation arrays
storage/                 cache, logs, compiled views (generated — never commit)
```

## Golden rules

1. **SQL lives in models.** Controllers call model methods; models use
   `$this->table()` (the query builder) or `$this->query($sql, $params)`.
2. **Whitelist input before writing.** Pick fields explicitly —
   `create(['name' => $data['name'], …])` — never pass `getBody()` straight to
   `create()`/`save()`/`update()` (models have no `$fillable` guard).
3. **Every state-changing form carries `@csrf`;** AJAX sends the token in the
   `X-CSRF-Token` header. CSRF is enforced globally.
4. **Escape all output with `{{ }}`.** Use `{!! !!}` only for HTML you built
   and trust.
5. **After login, call `$this->session->regenerate()` before setting `user_id`.**
   Store passwords with `password_hash()`, check them with `password_verify()`.
6. **Validate every input** with a FormRequest (preferred) or `$this->validate()`.
7. **Configuration comes from `config('key.sub')`**, file paths from
   `Spartan\Paths` (`Paths::storage('x')`, `Paths::base('x')`).
8. Use `declare(strict_types=1);`, typed parameters and return types in every file.

## Laravel habits → the Spartan way

| Laravel habit                         | Spartan                                                    |
|---------------------------------------|------------------------------------------------------------|
| `where('age', '>', 18)`               | `where('age', 18, '>')` — value **before** operator          |
| `User::find(1)` (static)              | `(new User())->find(1)` → **array** or null                  |
| model object                          | `(new User())->findInstance(1)` / `findInstanceBy('email', $e)` |
| `User::create([...])`                 | `(new User())->create([...])` → new id (string)              |
| `$user->update([...])`                | `(new User())->save($id, [...])` → affected rows             |
| `$user->posts` (relation property)    | `(new User())->posts()->for($user)` (see database guide)     |
| `Auth::attempt()`                     | manual: `findInstanceBy` + `password_verify` (auth guide)    |
| `auth()->user()` / `Auth::id()`       | `auth()->user()` / `auth()->id()` / `auth()->check()`        |
| `$request->validate([...])`           | FormRequest class, or `$this->validate($data, $rules)`       |
| `$errors` in views                    | `errors()` / `errors('email')` helper                        |
| `return redirect('/x')`               | `$this->redirect('/x'); return;` (action returns `void`)     |
| `return response()->json($d)`         | `$this->json($d, 200);` (action returns `void`)              |
| `return view('x', $d)`                | `return $this->render('x', $d);` (action returns `string`)   |
| `php artisan make:*`                  | create the file by hand from the guide's template            |
| `php artisan migrate`                 | `php spartan migrate`                                        |
| service providers                     | register in `public/index.php` after the Application         |

## Feature workflow — follow in order

1. **Migration**: `database/migrations/NNNN_name.sql` (+ `_down.sql`), then
   `php spartan migrate`.
2. **Model** in `src/Models/` with `protected string $table`.
3. **FormRequest** in `src/Controllers/Requests/` for each form.
4. **Service** in `src/Services/` when logic spans several models.
5. **Controller** action in `src/Controllers/`.
6. **Route** in `routes/web.php` (or `api.php`), with middleware.
7. **View** in `src/Views/` extending a layout.
8. **Test** in `tests/Unit/` for the new behaviour.

The feature is done when all three checks below pass.

## Checks — run after every change

```bash
vendor/bin/phpunit                                  # all green
vendor/bin/phpstan analyse --memory-limit=512M      # [OK] No errors
php tests/run_tests.php                             # kernel suite, 0 failed
```

Run an app locally: `php -S localhost:8000 -t public`.
Working reference apps (copy their patterns): `examples/blogger` (auth, roles,
forms, JSON API, queue) and `examples/taskforge` (projects, tasks, policies,
HTMX). Each runs with zero setup: `cd examples/blogger && php -S localhost:8086 -t public`.
