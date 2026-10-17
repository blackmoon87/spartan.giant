{{-- Needs $page (QueryBuilder::paginate() result), $baseUrl and $query (filters to keep). --}}
@if($page['last_page'] > 1)
<nav class="pagination" aria-label="@lang('app.pagination.label')">
    <span>
        @if($page['current_page'] > 1)
            <a class="btn btn-secondary btn-small" href="{{ url($baseUrl) . '?' . http_build_query(array_merge($query, ['page' => $page['current_page'] - 1])) }}">@lang('app.pagination.previous')</a>
        @endif
    </span>
    <span class="muted small">{{ __('app.pagination.summary', ['page' => $page['current_page'], 'last' => $page['last_page'], 'total' => $page['total']]) }}</span>
    <span>
        @if($page['current_page'] < $page['last_page'])
            <a class="btn btn-secondary btn-small" href="{{ url($baseUrl) . '?' . http_build_query(array_merge($query, ['page' => $page['current_page'] + 1])) }}">@lang('app.pagination.next')</a>
        @endif
    </span>
</nav>
@endif
