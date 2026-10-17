@extends('layouts.app')
@section('title', trans('tickets.index_title'))

@section('content')
<div class="page-head">
    <h1>@lang('tickets.index_title')</h1>
    <a class="btn" href="{{ url('/tickets/create') }}">@lang('app.nav.new_ticket')</a>
</div>

<form method="GET" action="{{ url('/tickets') }}" class="card filters">
    <div class="form-group">
        <label for="q">@lang('tickets.search_label')</label>
        <input type="search" id="q" name="q" value="{{ $filters['q'] }}" maxlength="100">
    </div>
    <div class="form-group">
        <label for="status">@lang('tickets.filter_status')</label>
        <select id="status" name="status">
            <option value="">@lang('tickets.any_status')</option>
            @foreach(\App\Models\Ticket::STATUSES as $status)
                <option value="{{ $status }}" @selected($filters['status'] === $status)>{{ trans('app.status.' . $status) }}</option>
            @endforeach
        </select>
    </div>
    <div class="form-group">
        <label for="sort">@lang('tickets.sort_label')</label>
        <select id="sort" name="sort">
            @foreach(['newest', 'oldest', 'updated'] as $sort)
                <option value="{{ $sort }}" @selected($filters['sort'] === $sort)>{{ trans('tickets.sort.' . $sort) }}</option>
            @endforeach
        </select>
    </div>
    @if($isStaff)
        <div class="form-group">
            <label class="check"><input type="checkbox" name="mine" value="1" @checked($mine)> @lang('tickets.only_mine')</label>
        </div>
    @endif
    <div class="actions">
        <button type="submit" class="btn">@lang('app.search')</button>
        <a href="{{ url('/tickets') }}">@lang('app.clear')</a>
    </div>
</form>

@empty($page['data'])
    <div class="card"><p>@lang('tickets.empty')</p></div>
@endempty
@if(!empty($page['data']))
    <ul class="ticket-list">
        @foreach($page['data'] as $ticket)
            @include('partials.ticket_item', ['ticket' => $ticket])
        @endforeach
    </ul>
@endif

@include('partials.pagination', ['page' => $page, 'baseUrl' => '/tickets', 'query' => $query])
@endsection
