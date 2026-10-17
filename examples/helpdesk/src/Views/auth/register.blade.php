@extends('layouts.app')
@section('title', trans('auth.register_title'))

@section('content')
<section class="card card-narrow">
    <h1>@lang('auth.register_title')</h1>
    <p class="lead">@lang('auth.register_lead')</p>

    <form method="POST" action="{{ url('/register') }}" class="stack">
        @csrf
        <div class="form-group">
            <label for="name">@lang('auth.name')</label>
            <input type="text" id="name" name="name" value="{{ old('name', '') }}" autocomplete="name" maxlength="100" required aria-invalid="{{ errors('name') ? 'true' : 'false' }}">
            @if(errors('name'))<p class="field-error">{{ errors('name') }}</p>@endif
        </div>
        <div class="form-group">
            <label for="email">@lang('auth.email')</label>
            <input type="email" id="email" name="email" value="{{ old('email', '') }}" autocomplete="email" required aria-invalid="{{ errors('email') ? 'true' : 'false' }}">
            @if(errors('email'))<p class="field-error">{{ errors('email') }}</p>@endif
        </div>
        <div class="form-group">
            <label for="password">@lang('auth.password')</label>
            <input type="password" id="password" name="password" autocomplete="new-password" minlength="8" required aria-invalid="{{ errors('password') ? 'true' : 'false' }}">
            @if(errors('password'))<p class="field-error">{{ errors('password') }}</p>@endif
        </div>
        <div class="form-group">
            <label for="password_confirmation">@lang('auth.password_confirm')</label>
            <input type="password" id="password_confirmation" name="password_confirmation" autocomplete="new-password" required>
        </div>
        <div class="actions">
            <button type="submit" class="btn">@lang('auth.register_button')</button>
            <span class="muted small">@lang('auth.have_account') <a href="{{ url('/login') }}">@lang('app.nav.login')</a></span>
        </div>
    </form>
</section>
@endsection
