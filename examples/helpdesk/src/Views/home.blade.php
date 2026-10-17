@extends('layouts.app')
@section('title', trans('home.title'))

@section('content')
<section class="card">
    <h1>@lang('home.heading')</h1>
    <p class="lead">@lang('home.lead')</p>
    <ul class="feature-list">
        <li>@lang('home.features.auth')</li>
        <li>@lang('home.features.policies')</li>
        <li>@lang('home.features.uploads')</li>
        <li>@lang('home.features.events')</li>
        <li>@lang('home.features.cache')</li>
        <li>@lang('home.features.api')</li>
    </ul>
    <div class="actions">
        <a class="btn" href="{{ url('/login') }}">@lang('home.cta')</a>
        <a class="btn btn-secondary" href="{{ url('/register') }}">@lang('app.nav.register')</a>
    </div>
</section>
@endsection
