@extends('layouts.main')
@section('title', trans('site.errors.403_title'))

@section('content')
<div class="glass-card centered">
    <h1 class="hero-title error-code">403</h1>
    <h2 class="error-title">@lang('site.errors.403_title')</h2>
    <p class="lead">{{ $message ?? trans('site.errors.403_body') }}</p>
    <a href="{{ url('/') }}" class="btn">@lang('site.errors.home')</a>
</div>
@endsection
