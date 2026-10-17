@extends('layouts.main')
@section('title', trans('site.errors.404_title'))

@section('content')
<div class="glass-card centered">
    <h1 class="hero-title error-code">404</h1>
    <h2 class="error-title">@lang('site.errors.404_title')</h2>
    <p class="lead">{{ $message ?? trans('site.errors.404_body') }}</p>
    <a href="{{ url('/') }}" class="btn">@lang('site.errors.home')</a>
</div>
@endsection
