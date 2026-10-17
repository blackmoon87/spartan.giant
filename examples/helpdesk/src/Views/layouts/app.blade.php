<!DOCTYPE html>
<html lang="{{ current_locale() }}" dir="{{ is_rtl() ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ $this->csrfTokenValue() }}">
    <title>@yield('title') · @lang('app.name')</title>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body>
@php $path = $currentPath ?? ''; @endphp
<header class="site-header">
    <div class="container">
        <a class="brand" href="{{ url('/') }}">@lang('app.name')</a>
        <nav aria-label="@lang('app.nav.label')">
            <ul class="nav">
                @auth
                    <li><a href="{{ url('/dashboard') }}" class="{{ $path === '/dashboard' ? 'active' : '' }}">@lang('app.nav.dashboard')</a></li>
                    <li><a href="{{ url('/tickets') }}" class="{{ str_starts_with($path, '/tickets') && $path !== '/tickets/create' ? 'active' : '' }}">@lang('app.nav.tickets')</a></li>
                    <li><a href="{{ url('/tickets/create') }}" class="{{ $path === '/tickets/create' ? 'active' : '' }}">@lang('app.nav.new_ticket')</a></li>
                    <li>
                        <a href="{{ url('/notifications') }}" class="{{ $path === '/notifications' ? 'active' : '' }}">@lang('app.nav.notifications')@if(($unreadCount ?? 0) > 0)<span class="badge-count" aria-label="{{ __('app.nav.unread', ['count' => $unreadCount]) }}">{{ $unreadCount }}</span>@endif</a>
                    </li>
                    <li><a href="{{ url('/account/tokens') }}" class="{{ $path === '/account/tokens' ? 'active' : '' }}">@lang('app.nav.api_tokens')</a></li>
                    @role('admin')
                        <li><a href="{{ url('/admin/users') }}" class="{{ $path === '/admin/users' ? 'active' : '' }}">@lang('app.nav.admin')</a></li>
                    @endrole
                    <li>
                        <form method="POST" action="{{ url('/logout') }}">
                            @csrf
                            <button type="submit" class="link-button" title="{{ __('app.nav.signed_in_as', ['name' => $authUser->name]) }}">@lang('app.nav.logout')</button>
                        </form>
                    </li>
                @endauth
                @guest
                    <li><a href="{{ url('/login') }}" class="{{ $path === '/login' ? 'active' : '' }}">@lang('app.nav.login')</a></li>
                    <li><a href="{{ url('/register') }}" class="{{ $path === '/register' ? 'active' : '' }}">@lang('app.nav.register')</a></li>
                @endguest
            </ul>
        </nav>
    </div>
</header>

<main class="site-main">
    <div class="container">
        @flash('success')<div class="alert alert-success" role="status">{{ $flashMsg }}</div>@endflash
        @flash('error')<div class="alert alert-error" role="alert">{{ $flashMsg }}</div>@endflash
        @yield('content')
    </div>
</main>

<footer class="site-footer">
    <div class="container">@lang('app.footer')</div>
</footer>
</body>
</html>
