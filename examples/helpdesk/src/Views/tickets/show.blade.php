@extends('layouts.app')
@section('title', '#' . $ticket['id'] . ' ' . $ticket['subject'])

@section('content')
<div class="page-head">
    <div>
        <h1>#{{ $ticket['id'] }} · {{ $ticket['subject'] }}</h1>
        <p class="lead">{{ __('tickets.opened_by', ['name' => $owner['name'] ?? '', 'date' => substr((string) $ticket['created_at'], 0, 16)]) }}</p>
    </div>
    <div class="actions">
        @include('partials.status', ['status' => $ticket['status']])
        @include('partials.priority', ['priority' => $ticket['priority']])
    </div>
</div>

<div class="cols-main-side">
    <div class="stack">
        <section class="card">
            <p class="ticket-body">{{ $ticket['body'] }}</p>
        </section>

        @if(!empty($attachments))
            <section class="card">
                <h2>@lang('tickets.attachments')</h2>
                <ul class="file-list">
                    @foreach($attachments as $file)
                        <li><a href="{{ url('/attachments/' . $file['id']) }}">{{ $file['original_name'] }}</a> <span class="muted small">{{ number_format($file['size'] / 1024, 1) }} KB</span></li>
                    @endforeach
                </ul>
            </section>
        @endif

        <section id="replies" class="stack">
            <h2>@lang('tickets.replies')</h2>
            @empty($replies)
                <p class="muted">@lang('tickets.no_replies')</p>
            @endempty
            @if(!empty($replies))
                <ul class="replies">
                    @foreach($replies as $reply)
                        <li class="reply {{ $reply['is_internal'] ? 'reply-internal' : '' }}">
                            <div class="reply-head">
                                <strong>{{ $reply['author']['name'] ?? trans('tickets.someone') }}</strong>
                                <span class="muted">{{ substr((string) $reply['created_at'], 0, 16) }}</span>
                                @if($reply['is_internal'])<span class="badge badge-internal">@lang('tickets.internal_badge')</span>@endif
                            </div>
                            <p class="reply-body">{{ $reply['body'] }}</p>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>

        @can('reply', $ticketModel)
            <section class="card">
                <form method="POST" action="{{ url('/tickets/' . $ticket['id'] . '/replies') }}">
                    @csrf
                    <div class="form-group">
                        <label for="body">@lang('tickets.reply_label')</label>
                        <textarea id="body" name="body" rows="4" maxlength="10000" required aria-invalid="{{ errors('body') ? 'true' : 'false' }}">{{ old('body', '') }}</textarea>
                        @if(errors('body'))<p class="field-error">{{ errors('body') }}</p>@endif
                    </div>
                    @if($canManage)
                        <div class="form-group">
                            <label class="check"><input type="checkbox" name="internal" value="1"> @lang('tickets.reply_internal')</label>
                        </div>
                    @endif
                    <button type="submit" class="btn">@lang('tickets.reply_submit')</button>
                </form>
            </section>
        @endcan
        @cannot('reply', $ticketModel)
            <div class="alert alert-info">@lang('tickets.closed_notice')</div>
        @endcannot
    </div>

    <aside class="stack">
        <section class="card">
            <dl class="meta-list">
                <dt>@lang('tickets.col_owner')</dt>
                <dd>{{ $owner['name'] ?? '' }}</dd>
                <dt>@lang('tickets.assigned_to')</dt>
                <dd>{{ $assignee['name'] ?? trans('app.unassigned') }}</dd>
                <dt>@lang('tickets.col_updated')</dt>
                <dd>{{ substr((string) $ticket['updated_at'], 0, 16) }}</dd>
            </dl>
        </section>

        @if($canManage)
            <section class="card">
                <h2>@lang('tickets.manage')</h2>
                <form method="POST" action="{{ url('/tickets/' . $ticket['id']) }}">
                    @csrf
                    @method('PATCH')
                    <div class="form-group">
                        <label for="status">@lang('tickets.col_status')</label>
                        <select id="status" name="status">
                            @foreach(\App\Models\Ticket::STATUSES as $status)
                                <option value="{{ $status }}" @selected($ticket['status'] === $status)>{{ trans('app.status.' . $status) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="assignee_id">@lang('tickets.assigned_to')</label>
                        <select id="assignee_id" name="assignee_id">
                            <option value="">@lang('app.nobody')</option>
                            @foreach($staff as $staffId => $staffName)
                                <option value="{{ $staffId }}" @selected((int) $ticket['assignee_id'] === (int) $staffId)>{{ $staffName }}</option>
                            @endforeach
                        </select>
                    </div>
                    <button type="submit" class="btn">@lang('tickets.update_submit')</button>
                </form>
            </section>

            <section class="card">
                <h2>@lang('tickets.history')</h2>
                <ol class="timeline">
                    @foreach($activities as $activity)
                        <li>
                            {{ __('tickets.activity.' . $activity['action'], ['name' => $activity['actor']['name'] ?? trans('tickets.someone'), 'details' => $activity['action'] === 'status_changed' ? trans('app.status.' . $activity['details']) : (string) $activity['details']]) }}
                            <time>{{ substr((string) $activity['created_at'], 0, 16) }}</time>
                        </li>
                    @endforeach
                </ol>
            </section>
        @endif
    </aside>
</div>
@endsection
