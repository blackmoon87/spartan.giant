@extends('layouts.app')
@section('title', trans('dashboard.title'))

@section('content')
<div class="page-head">
    <div>
        <h1>@lang('dashboard.heading')</h1>
        <p class="lead">{{ $isStaff ? trans('dashboard.lead_staff') : trans('dashboard.lead_owner') }}</p>
    </div>
    <a class="btn" href="{{ url('/tickets/create') }}">@lang('app.nav.new_ticket')</a>
</div>

<div class="auto-grid">
    @foreach($stats['counts'] as $status => $count)
        <a class="card stat" href="{{ url('/tickets') . '?status=' . $status }}">
            <div class="stat-value">{{ $count }}</div>
            <div class="stat-label">{{ trans('app.status.' . $status) }}</div>
        </a>
    @endforeach
    @if($stats['unassigned'] !== null)
        <div class="card stat stat-highlight">
            <div class="stat-value">{{ $stats['unassigned'] }}</div>
            <div class="stat-label">@lang('dashboard.unassigned')</div>
        </div>
    @endif
</div>
<p class="muted small stats-note">@lang('dashboard.cache_note')</p>

<section class="stack section-gap">
    <div class="page-head">
        <h2>@lang('dashboard.recent')</h2>
        <a href="{{ url('/tickets') }}">@lang('dashboard.view_all')</a>
    </div>
    @empty($recent)
        <div class="card">
            <p>@lang('dashboard.empty')</p>
            <p><a class="btn" href="{{ url('/tickets/create') }}">@lang('dashboard.open_first')</a></p>
        </div>
    @endempty
    @if(!empty($recent))
        <ul class="ticket-list">
            @foreach($recent as $ticket)
                @include('partials.ticket_item', ['ticket' => $ticket])
            @endforeach
        </ul>
    @endif
</section>
@endsection
