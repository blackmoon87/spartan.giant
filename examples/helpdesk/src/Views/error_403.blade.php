@extends('layouts.app')
@section('title', trans('app.errors.403_title'))

@section('content')
<section class="card error-page">
    <p class="error-code">403</p>
    <h1>@lang('app.errors.403_title')</h1>
    <p class="lead">{{ $message ?? trans('app.errors.forbidden') }}</p>
    <div class="actions"><a class="btn" href="{{ url('/dashboard') }}">@lang('app.errors.home')</a></div>
</section>
@endsection
