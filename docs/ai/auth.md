# Auth — users, login, registration, logout, roles, permissions, gates

Source: `framework/src/Auth.php`, `Session.php`, `Gate.php`,
`Traits/HasAuthorization.php`, `Attributes/*`, `Middlewares/AuthMiddleware.php`,
working example: `examples/blogger/src/Controllers/AuthController.php`.

## How it works

- A user is logged in when the session holds `user_id`.
- `auth()->user()` loads that user as a model (class from `config('auth.model')`,
  default `App\Models\User`), cached for the request. `auth()->id()`,
  `auth()->check()`. Views get the same object as `$authUser`.
- Login, registration and logout are written in your controllers with the
  recipes below — there is no `Auth::attempt()`, password reset or email
  verification built in (no mailer).

## Tables

`database/migrations/0002_create_auth_tables.sql` (run `php spartan migrate`) creates:

```
users            id, name, email (unique), password, created_at, updated_at
roles            id, name, slug (unique)
permissions      id, name, slug (unique)
role_permissions role_id, permission_id
user_roles       user_id, role_id
```

Seed roles and permissions (e.g. in `database/seed.sql`):

```sql
INSERT INTO roles (id, name, slug) VALUES (1, 'Admin', 'admin'), (2, 'Member', 'member');
INSERT INTO permissions (id, name, slug) VALUES (1, 'Manage users', 'manage_users'), (2, 'Publish posts', 'publish_posts');
INSERT INTO role_permissions (role_id, permission_id) VALUES (1, 1), (1, 2), (2, 2);
```

## User model

```php
<?php

declare(strict_types=1);

namespace App\Models;

use Spartan\Model;

class User extends Model
{
    use \Spartan\Traits\HasAuthorization;    // hasRole(), hasPermission(), assignRole()

    protected string $table = 'users';
    protected bool $timestamps = true;
}
```

## Routes

```php
// routes/web.php
use App\Controllers\AuthController;

$app->router->aliasMiddleware('throttle', \Spartan\Middlewares\RateLimitMiddleware::class);

$app->router->get('/register', [AuthController::class, 'showRegister']);
$app->router->post('/register', [AuthController::class, 'register'], ['throttle:5,60']);
$app->router->get('/login', [AuthController::class, 'showLogin']);       // keep this path: guards redirect to /login
$app->router->post('/login', [AuthController::class, 'login'], ['throttle:5,60']);
$app->router->post('/logout', [AuthController::class, 'logout']);
```

## Form requests

```php
// src/Controllers/Requests/RegisterRequest.php
class RegisterRequest extends \Spartan\FormRequest
{
    public function rules(): array
    {
        return [
            'name'     => 'required|string|min:2|max:100',
            'email'    => 'required|email|unique:users,email',
            'password' => 'required|string|min:8|confirmed',   // needs password_confirmation
        ];
    }
}

// src/Controllers/Requests/LoginRequest.php
class LoginRequest extends \Spartan\FormRequest
{
    public function rules(): array
    {
        return ['email' => 'required|email', 'password' => 'required|string'];
    }
}
```

(Each class in its own file with `declare(strict_types=1);` and
`namespace App\Controllers\Requests;`.)

## Controller

```php
<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Controllers\Requests\LoginRequest;
use App\Controllers\Requests\RegisterRequest;
use App\Models\User;
use Spartan\Controller;

class AuthController extends Controller
{
    public function showRegister(): string
    {
        return $this->render('auth/register');
    }

    public function register(RegisterRequest $request): void
    {
        $data = $request->getBody();

        $id = (new User())->create([
            'name'     => trim((string) $data['name']),
            'email'    => strtolower(trim((string) $data['email'])),
            'password' => password_hash((string) $data['password'], PASSWORD_DEFAULT),
        ]);

        (new User())->findInstance($id)?->assignRole('member');   // role must exist

        $this->session->regenerate();                 // new session id on privilege change
        $this->session->set('user_id', (int) $id);
        $this->redirect('/');
    }

    public function showLogin(): string
    {
        return $this->render('auth/login');
    }

    public function login(LoginRequest $request): void
    {
        $email    = strtolower(trim((string) $request->post('email')));
        $password = (string) $request->post('password');

        $user = (new User())->findInstanceBy('email', $email);

        if ($user === null || !password_verify($password, (string) $user->password)) {
            $this->session->setFlash('error', 'Invalid email or password.');
            $this->redirect('/login');
            return;
        }

        if (password_needs_rehash((string) $user->password, PASSWORD_DEFAULT)) {
            (new User())->save($user->id, ['password' => password_hash($password, PASSWORD_DEFAULT)]);
        }

        $this->session->regenerate();
        $this->session->set('user_id', (int) $user->id);
        $this->redirect('/');
    }

    public function logout(): void
    {
        $this->session->remove('user_id');
        $this->session->regenerate();
        $this->redirect('/');
    }
}
```

Login view (`src/Views/auth/login.blade.php`):

```blade
@extends('layouts.app')
@section('title', 'Log in')
@section('content')
    @flash('error')<p class="error">{{ $flashMsg }}</p>@endflash
    <form method="POST" action="/login">
        @csrf
        <input type="email" name="email" value="{{ old('email') }}" required>
        @if(errors('email'))<p class="error">{{ errors('email') }}</p>@endif
        <input type="password" name="password" required>
        <button>Log in</button>
    </form>
@endsection
```

Logout button (POST, never a GET link):

```blade
@auth
    <form method="POST" action="/logout">@csrf <button>Log out</button></form>
@endauth
```

## Protecting pages

| Need | Use |
|---|---|
| logged in | route middleware `[\Spartan\Middlewares\AuthMiddleware::class]` → guests go to `/login` |
| a role (any of several) | `#[\Spartan\Attributes\RequireRole('admin', 'editor')]` on the controller class or action |
| permissions (all listed) | `#[\Spartan\Attributes\RequirePermission('publish_posts')]` |
| a rule about a specific record | Gate (below) |
| inside an action | `if (!auth()->check()) { $this->redirect('/login'); return; }` |

With an attribute, guests are redirected to `/login` (AJAX: `401` JSON) and
users lacking the role/permission get `403` (`error_403` view; AJAX: JSON).

`HasAuthorization` methods on a user model: `hasRole(string ...$slugs): bool`
(any), `hasPermission(string $slug): bool`, `assignRole(string $slug): void`
(throws when the role does not exist), `getRoles(): array`, `getPermissions(): array`.

## Gates and policies

Register in `public/index.php`, after the Application is created:

```php
use Spartan\Gate;

Gate::define('edit-post', function (?object $user, array $post): bool {
    return $user !== null && (int) $post['user_id'] === (int) $user->id;
});

Gate::policy(\App\Models\Post::class, \App\Policies\PostPolicy::class);
```

A policy is a plain class; each method is an ability receiving `(?object $user, …args)`:

```php
namespace App\Policies;

class PostPolicy
{
    public function update(?object $user, \App\Models\Post $post): bool
    {
        return $user !== null && (int) $post->user_id === (int) $user->id;
    }
}
```

Policies are matched by the **class of the first argument**, so pass a model
object (`findInstance`) or the class name (`Gate::check('create', Post::class)`).

Checking:

```php
Gate::allows('edit-post', $post);     Gate::denies('edit-post', $post);
Gate::check('update', $postModel);    Gate::forUser($otherUser)->allows('update', $postModel);
```

```blade
@can('edit-post', $post) <a href="/posts/{{ $post['id'] }}/edit">Edit</a> @endcan
@role('admin') <a href="/admin">Admin</a> @endrole
```

## Session API — `$this->session`

`set($k, $v)`, `get($k, $default)`, `remove($k)`, `setFlash($k, $msg)`,
`getFlash($k, $default)`, `regenerate()`, `destroy()`. Flash values live for
the next request only. The session cookie is HttpOnly, SameSite=Lax, and Secure
on HTTPS.
