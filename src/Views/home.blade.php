@extends('layouts.main')
@section('title', trans('site.home.title'))

@section('content')
<div class="glass-card">
    <div class="card-head">
        <h1 class="hero-title">@lang('site.home.heading')</h1>
        <span class="status-pill success">@lang('site.home.badge')</span>
    </div>

    <p class="lead">@lang('site.home.lead')</p>

    <div class="tile-grid">
        <div class="tile">
            <h3>@lang('site.home.router_title')</h3>
            <p>@lang('site.home.router_body')</p>
        </div>

        <div class="tile">
            <h3>@lang('site.home.mvc_title')</h3>
            <p>@lang('site.home.mvc_body')</p>
        </div>

        <div class="tile">
            <h3>@lang('site.home.db_title')</h3>
            <p>@lang('site.home.db_status') <strong>{{ trans('site.home.db_' . $dbStatus) }}</strong></p>
            <p>@lang('site.home.users_found') <code class="count">{{ (int) $userCount }}</code></p>
        </div>
    </div>

    <div class="section">
        <h3>@lang('site.home.routes_title')</h3>
        <p class="muted">@lang('site.home.routes_body')</p>
        <div class="actions">
            <a href="{{ url('/profile/admin') }}" class="btn secondary">@lang('site.home.try_profile')</a>
            <a href="{{ url('/contact') }}" class="btn">@lang('site.home.try_contact')</a>
        </div>
    </div>
</div>
@endsection
