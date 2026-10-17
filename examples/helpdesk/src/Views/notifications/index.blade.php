@extends('layouts.app')
@section('title', trans('notifications.title'))

@section('content')
<div class="page-head">
    <div>
        <h1>@lang('notifications.title')</h1>
        <p class="lead small">@lang('notifications.worker_hint')</p>
    </div>
    @if(($unreadCount ?? 0) > 0)
        <form method="POST" action="{{ url('/notifications/read') }}">
            @csrf
            <button type="submit" class="btn btn-secondary">@lang('notifications.mark_read')</button>
        </form>
    @endif
</div>

@empty($items)
    <div class="card"><p>@lang('notifications.empty')</p></div>
@endempty
@if(!empty($items))
    <ul class="ticket-list">
        @foreach($items as $item)
            <li class="card ticket-item">
                <div>
                    <a class="subject" href="{{ url('/tickets/' . $item['ticket_id']) }}">{{ $item['message'] }}</a>
                    <div class="ticket-meta"><span>{{ substr((string) $item['created_at'], 0, 16) }}</span></div>
                </div>
                @if($item['unread'])<span class="badge status-open">@lang('notifications.new')</span>@endif
            </li>
        @endforeach
    </ul>
@endif
@endsection
