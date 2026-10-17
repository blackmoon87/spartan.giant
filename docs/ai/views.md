# Views — Blade templates, layouts, forms, translation

Source: `framework/src/View.php` (the compiler is `compileString()`), `helpers.php`,
`Translation/Translator.php`.

## Files and names

`$this->render('posts/show', $data)` (or `'posts.show'`) loads
`src/Views/posts/show.blade.php`. Write every view as `.blade.php`.

## Layout + page

`src/Views/layouts/app.blade.php`:

```blade
<!DOCTYPE html>
<html lang="{{ current_locale() }}" dir="{{ is_rtl() ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="UTF-8">
    <meta name="csrf-token" content="{{ $this->csrfTokenValue() }}">
    <title>@yield('title')</title>
</head>
<body>
    @flash('success')<div class="alert">{{ $flashMsg }}</div>@endflash
    @yield('content')
</body>
</html>
```

`src/Views/posts/index.blade.php`:

```blade
@extends('layouts.app')
@section('title', trans('posts.all_posts'))

@section('content')
    <h1>@lang('posts.all_posts')</h1>
    @foreach($posts as $post)
        @include('posts.card', ['post' => $post])
    @endforeach
@endsection
```

- One level of `@extends`: a page extends a layout; a layout does not extend another.
- `@yield('name')` has no default value — always define the section, or set the
  value in PHP and pass it as a variable.
- `@include('name')` passes all current variables; `@include('name', ['k' => $v])`
  adds more. Keep include arguments to plain values and variables.

## Directives — this is the complete list

| Directive | Meaning |
|---|---|
| `{{ $x }}` | escaped output (null prints nothing) |
| `{!! $x !!}` | raw output — trusted HTML only |
| `{{-- note --}}` | comment, removed |
| `@if(…)` `@elseif(…)` `@else` `@endif` | conditionals |
| `@foreach(… as …)` `@endforeach` | loop |
| `@for(…)` `@endfor` · `@while(…)` `@endwhile` | loops |
| `@empty($list)` … `@endempty` | runs when `$list` is empty |
| `@extends('layout')` | use a layout |
| `@section('name')` … `@endsection` · `@section('name', $value)` | fill a section |
| `@yield('name')` | print a section (in layouts) |
| `@include('view', [...])` | render a partial |
| `@csrf` | hidden `_csrf` input |
| `@method('PUT')` | hidden `_method` input |
| `@flash('key')` … `{{ $flashMsg }}` … `@endflash` | show a flash **string** |
| `@auth` … `@endauth` · `@guest` … `@endguest` | logged in / not |
| `@can('ability', $model)` … `@endcan` · `@cannot(…)` … `@endcannot` | Gate checks |
| `@role('admin')` … `@endrole` | user has the role (needs `HasAuthorization`) |
| `@lang('app.key')` · `@lang('app.key', ['name' => $n])` | escaped translation |
| `@selected($cond)` · `@checked($cond)` · `@disabled($cond)` | form attributes |
| `@escape($x)` | escaped output, same as `{{ }}` |
| `@php` … `@endphp` | raw PHP block |
| `@@if` · `@{{ x }}` | print `@if` / `{{ x }}` literally |

There is no `@forelse`, `@isset`, `@unless`, `@switch`, `@props` or components:
write `@if(…)`, `@empty(…)` or `@php` instead.

Do not define closures (`fn () =>`, `function () {}`) inside `@php`: without
OPcache every render keeps the closure's compiled code, so memory grows in
worker mode. Compute values in the controller, or use a plain loop.

## Variables available in every view

- everything passed to `render()`
- `$authUser` — the logged-in user object or `null`
- values shared with `$this->share('key', $v)` (controller) or
  `Application::$app->view->share('key', $v)`
- `$this` is the View: `$this->csrfTokenValue()`

Helpers: `old('field')`, `errors()` / `errors('field')`, `url('/path')`,
`asset('css/app.css')`, `trans('app.key', [...])` (alias `__()`), `current_locale()`,
`is_rtl()`, `auth()->user()`, `config('app.name')`.

## Forms

```blade
<form method="POST" action="/posts">
    @csrf
    <label>@lang('posts.title') <input name="title" value="{{ old('title') }}"></label>
    @if(errors('title'))<p class="error">{{ errors('title') }}</p>@endif

    <select name="status">
        <option value="draft" @selected(old('status') === 'draft')>@lang('posts.status_draft')</option>
        <option value="published" @selected(old('status') === 'published')>@lang('posts.status_published')</option>
    </select>
    <button>@lang('app.actions.save')</button>
</form>

{{-- edit form --}}
<form method="POST" action="/posts/{{ $post['id'] }}">
    @csrf
    @method('PUT')
    …
</form>
```

After a failed FormRequest the browser is redirected back; `old()` and
`errors()` then return the submitted values and messages.

AJAX (fetch) with CSRF:

```js
fetch('/posts/5/like', {
  method: 'POST',
  headers: { 'X-CSRF-Token': document.querySelector('meta[name=csrf-token]').content,
             'X-Requested-With': 'XMLHttpRequest' },
});
```

HTMX partials: return `$this->renderViewOnly('posts.row', $data)` (no layout).

## Translation — Mandatory for All Frontend & User-Facing Text

**Zero hardcoded strings policy**: All visible text in Blade views (titles,
headings, button labels, form placeholders, empty states, and badges) as well as
all backend-to-frontend messages (session flash alerts, redirect messages, JSON
API status/error messages) **MUST** use Spartan's translation engine.

`lang/en/app.php`:

```php
<?php
return [
    'welcome'  => 'Welcome, :name!',
    'nav'      => ['home' => 'Home'],
    'actions'  => ['save' => 'Save', 'cancel' => 'Cancel'],
    'messages' => [
        'saved'     => 'Changes saved successfully.',
        'not_found' => 'The requested resource was not found.',
    ],
];
```

Usage patterns:
- **In Blade views**: `@lang('app.nav.home')` or `{{ __('app.welcome', ['name' => $user->name]) }}`.
- **In Controllers (flash messages)**: `$this->session->setFlash('success', trans('app.messages.saved'));`.
- **In Controllers (JSON / API responses)**: `$this->json(['message' => trans('app.messages.saved')], 200);` or `['error' => trans('app.messages.not_found')]`.

Framework messages (validation, 401/403/404/429/500, CSRF) live in the
`spartan` group, bundled in `framework/lang/{en,ar}/spartan.php`; an app's
`lang/{locale}/spartan.php` overrides individual keys. Missing keys print the key itself. The locale comes from the session key
`locale` on each request (default `APP_LOCALE`); switch with
`$this->session->set('locale', 'ar');` then redirect. `php spartan lang:check`
lists keys missing in any locale.

## Compiled view cache

Templates compile to `storage/views/`. `VIEW_CACHE_ENABLED=false` (development)
recompiles changed templates automatically. `VIEW_CACHE_ENABLED=true`
(production) skips the change check — run `php spartan view:clear` after
deploying template changes.
