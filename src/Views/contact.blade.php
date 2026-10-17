@extends('layouts.main')
@section('title', trans('site.contact.title'))

@section('content')
@php
    $fieldErrors = errors();
    // A loop, not a closure: a closure in a template keeps its compiled code
    // alive on every render when OPcache is off (memory growth in worker mode).
    $oldInput = [];
    foreach (['name', 'email', 'message'] as $field) {
        $value = old($field);
        $oldInput[$field] = is_string($value) ? $value : '';
    }
@endphp
<div class="glass-card">
    <h1 class="hero-title">@lang('site.contact.title')</h1>
    <p class="lead">@lang('site.contact.lead')</p>

    @flash('success')<div class="alert success" role="status">{{ $flashMsg }}</div>@endflash
    @if(!empty($fieldErrors))<div class="alert error" role="alert">@lang('site.contact.invalid')</div>@endif

    <form action="{{ url('/contact') }}" method="post" novalidate>
        @csrf
        <div class="form-group">
            <label for="name">@lang('site.contact.name')</label>
            <input type="text" id="name" name="name" value="{{ $oldInput['name'] }}" placeholder="@lang('site.contact.name_placeholder')" maxlength="100" required aria-invalid="{{ isset($fieldErrors['name']) ? 'true' : 'false' }}">
            @if(isset($fieldErrors['name']))<p class="field-error">{{ $fieldErrors['name'] }}</p>@endif
        </div>

        <div class="form-group">
            <label for="email">@lang('site.contact.email')</label>
            <input type="email" id="email" name="email" value="{{ $oldInput['email'] }}" placeholder="@lang('site.contact.email_placeholder')" maxlength="255" required aria-invalid="{{ isset($fieldErrors['email']) ? 'true' : 'false' }}">
            @if(isset($fieldErrors['email']))<p class="field-error">{{ $fieldErrors['email'] }}</p>@endif
        </div>

        <div class="form-group">
            <label for="message">@lang('site.contact.message')</label>
            <textarea id="message" name="message" rows="5" placeholder="@lang('site.contact.message_placeholder')" maxlength="5000" required aria-invalid="{{ isset($fieldErrors['message']) ? 'true' : 'false' }}">{{ $oldInput['message'] }}</textarea>
            @if(isset($fieldErrors['message']))<p class="field-error">{{ $fieldErrors['message'] }}</p>@endif
        </div>

        <div class="actions">
            <button type="submit" class="btn">@lang('site.contact.submit')</button>
            <a href="{{ url('/') }}" class="link-muted">@lang('site.contact.cancel')</a>
        </div>
    </form>
</div>
@endsection
