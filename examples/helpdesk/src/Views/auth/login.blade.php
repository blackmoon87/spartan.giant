@extends('layouts.app')
@section('title', trans('auth.login_title'))

@section('content')
<div class="auto-grid">
    <section class="card">
        <h1>@lang('auth.login_title')</h1>
        <p class="lead">@lang('auth.login_lead')</p>

        <form method="POST" action="{{ url('/login') }}" class="stack">
            @csrf
            <div class="form-group">
                <label for="email">@lang('auth.email')</label>
                <input type="email" id="email" name="email" value="{{ old('email', '') }}" autocomplete="username" required aria-invalid="{{ errors('email') ? 'true' : 'false' }}">
                @if(errors('email'))<p class="field-error">{{ errors('email') }}</p>@endif
            </div>
            <div class="form-group">
                <label for="password">@lang('auth.password')</label>
                <input type="password" id="password" name="password" autocomplete="current-password" required aria-invalid="{{ errors('password') ? 'true' : 'false' }}">
                @if(errors('password'))<p class="field-error">{{ errors('password') }}</p>@endif
            </div>
            <div class="actions">
                <button type="submit" class="btn">@lang('auth.login_button')</button>
                <span class="muted small">@lang('auth.no_account') <a href="{{ url('/register') }}">@lang('app.nav.register')</a></span>
            </div>
        </form>
    </section>

    <aside class="card">
        <h2>@lang('auth.demo_title')</h2>
        <p class="muted small">@lang('auth.demo_hint')</p>
        <div class="table-wrap">
            <table>
                <tbody>
                    @foreach($demoAccounts as $account)
                        <tr>
                            <td><code>{{ $account['email'] }}</code></td>
                            <td>{{ trans('app.role.' . $account['role']) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </aside>
</div>
@endsection
