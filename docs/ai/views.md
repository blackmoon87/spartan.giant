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
@section('title', 'All posts')

@section('content')
    <h1>Posts</h1>
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
    <label>Title <input name="title" value="{{ old('title') }}"></label>
    @if(errors('title'))<p class="error">{{ errors('title') }}</p>@endif

    <select name="status">
        <option value="draft" @selected(old('status') === 'draft')>Draft</option>
        <option value="published" @selected(old('status') === 'published')>Published</option>
    </select>
    <button>Save</button>
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

## Translation

`lang/en/app.php`:

```php
<?php
return ['welcome' => 'Welcome, :name!', 'nav' => ['home' => 'Home']];
```

Use `trans('app.welcome', ['name' => $user->name])` or `@lang('app.nav.home')`.
Missing keys print the key itself. The locale comes from the session key
`locale` on each request (default `APP_LOCALE`); switch with
`$this->session->set('locale', 'ar');` then redirect. `php spartan lang:check`
lists keys missing in any locale.

## Compiled view cache

Templates compile to `storage/views/`. `VIEW_CACHE_ENABLED=false` (development)
recompiles changed templates automatically. `VIEW_CACHE_ENABLED=true`
(production) skips the change check — run `php spartan view:clear` after
deploying template changes.
