@extends('layouts.app')
@section('title', trans('tickets.create_title'))

@section('content')
<section class="card card-narrow">
    <h1>@lang('tickets.create_title')</h1>
    <p class="lead">@lang('tickets.create_lead')</p>

    {{-- multipart: the optional attachment travels with the form. --}}
    <form method="POST" action="{{ url('/tickets') }}" enctype="multipart/form-data" class="stack">
        @csrf
        <div class="form-group">
            <label for="subject">@lang('tickets.subject')</label>
            <input type="text" id="subject" name="subject" value="{{ old('subject', '') }}" maxlength="200" required aria-invalid="{{ errors('subject') ? 'true' : 'false' }}">
            @if(errors('subject'))<p class="field-error">{{ errors('subject') }}</p>@endif
        </div>
        <div class="form-group">
            <label for="priority">@lang('tickets.priority')</label>
            <select id="priority" name="priority" aria-invalid="{{ errors('priority') ? 'true' : 'false' }}">
                @foreach(\App\Models\Ticket::PRIORITIES as $priority)
                    <option value="{{ $priority }}" @selected(old('priority', 'normal') === $priority)>{{ trans('app.priority.' . $priority) }}</option>
                @endforeach
            </select>
            @if(errors('priority'))<p class="field-error">{{ errors('priority') }}</p>@endif
        </div>
        <div class="form-group">
            <label for="body">@lang('tickets.body')</label>
            <textarea id="body" name="body" rows="7" maxlength="10000" required aria-invalid="{{ errors('body') ? 'true' : 'false' }}">{{ old('body', '') }}</textarea>
            @if(errors('body'))<p class="field-error">{{ errors('body') }}</p>@endif
        </div>
        <div class="form-group">
            <label for="attachment">@lang('tickets.attachment')</label>
            <input type="file" id="attachment" name="attachment" accept=".png,.jpg,.jpeg,.gif,.webp,.pdf,.txt">
            <p class="hint">@lang('tickets.attachment_hint')</p>
        </div>
        <div class="actions">
            <button type="submit" class="btn">@lang('tickets.submit')</button>
            <a href="{{ url('/tickets') }}">@lang('app.cancel')</a>
        </div>
    </form>
</section>
@endsection
