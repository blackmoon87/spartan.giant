@extends('layouts.main')
@section('title', trans('site.profile.title'))

@section('content')
<div class="glass-card">
    <h1 class="hero-title">@lang('site.profile.heading')</h1>
    <p class="lead">@lang('site.profile.lead')</p>

    <div class="tile">
        <p class="capture-label">@lang('site.profile.capture')</p>
        <p class="capture-value">@lang('site.profile.user_id') <span>{{ $userId }}</span></p>
    </div>

    <div class="actions profile-actions">
        <a href="{{ url('/') }}" class="btn secondary">@lang('site.profile.back')</a>
        <a href="{{ url('/profile/some-other-uuid') }}" class="btn">@lang('site.profile.another')</a>
    </div>
</div>
@endsection
