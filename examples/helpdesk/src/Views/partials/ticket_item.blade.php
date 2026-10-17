{{-- One ticket in a list. Needs $ticket (row with 'owner' and 'assignee' loaded). --}}
<li class="card ticket-item">
    <div>
        <a class="subject" href="{{ url('/tickets/' . $ticket['id']) }}">#{{ $ticket['id'] }} · {{ $ticket['subject'] }}</a>
        <div class="ticket-meta">
            <span>{{ $ticket['owner']['name'] ?? trans('tickets.someone') }}</span>
            <span>{{ $ticket['assignee']['name'] ?? trans('app.unassigned') }}</span>
            <span>{{ substr((string) $ticket['updated_at'], 0, 16) }}</span>
        </div>
    </div>
    <div class="actions">
        @include('partials.status', ['status' => $ticket['status']])
        @include('partials.priority', ['priority' => $ticket['priority']])
    </div>
</li>
