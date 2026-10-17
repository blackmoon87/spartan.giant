@extends('layouts.app')
@section('title', trans('app.errors.404_title'))

@section('content')
<section class="card error-page">
    <p class="error-code">404</p>
    <h1>@lang('app.errors.404_title')</h1>
    <p class="lead">{{ $message ?? trans('app.errors.not_found') }}</p>
    <div class="actions"><a class="btn" href="{{ url('/dashboard') }}">@lang('app.errors.home')</a></div>
</section>
@endsection
