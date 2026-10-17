@extends('layouts.app')
@section('title', trans('app.errors.500_title'))

@section('content')
<section class="card error-page">
    <p class="error-code">500</p>
    <h1>@lang('app.errors.500_title')</h1>
    <p class="lead">{{ $message ?? trans('app.errors.server') }}</p>
    <div class="actions"><a class="btn" href="{{ url('/dashboard') }}">@lang('app.errors.home')</a></div>
</section>
@endsection
