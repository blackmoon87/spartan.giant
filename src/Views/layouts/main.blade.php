<!DOCTYPE html>
<html lang="{{ current_locale() }}" dir="{{ is_rtl() ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ $this->csrfTokenValue() }}">
    <title>@yield('title') · @lang('site.brand')</title>
    <link rel="icon" href="{{ asset('favicon.png') }}">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body>
    <header class="site-header">
        <div class="nav-container">
            <a href="{{ url('/') }}" class="logo">@lang('site.brand')</a>
            <nav class="site-nav" aria-label="@lang('site.nav.label')">
                <ul>
                    <li><a href="{{ url('/') }}" class="{{ ($active ?? '') === 'home' ? 'active' : '' }}">@lang('site.nav.home')</a></li>
                    <li><a href="{{ url('/contact') }}" class="{{ ($active ?? '') === 'contact' ? 'active' : '' }}">@lang('site.nav.contact')</a></li>
                    <li><a href="{{ url('/profile/demo-user') }}" class="{{ ($active ?? '') === 'profile' ? 'active' : '' }}">@lang('site.nav.profile')</a></li>
                </ul>
            </nav>
        </div>
    </header>

    <main class="site-main">
        @yield('content')
    </main>

    <footer class="site-footer">
        <p>{{ __('site.footer', ['year' => date('Y')]) }}</p>
    </footer>
</body>
</html>
