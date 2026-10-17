@extends('layouts.app')
@section('title', trans('account.tokens_title'))

@section('content')
<div class="page-head">
    <div>
        <h1>@lang('account.tokens_title')</h1>
        <p class="lead">@lang('account.tokens_lead')</p>
    </div>
</div>

@if($newToken)
    <div class="alert alert-info" role="status">
        @lang('account.new_token')
        <code class="token-value">{{ $newToken }}</code>
    </div>
@endif

<div class="cols-main-side">
    <section class="card">
        @empty($tokens)
            <p class="muted">@lang('account.none')</p>
        @endempty
        @if(!empty($tokens))
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>@lang('account.col_name')</th>
                            <th>@lang('account.col_created')</th>
                            <th>@lang('account.col_used')</th>
                            <th><span class="visually-hidden">@lang('account.revoke')</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($tokens as $token)
                            <tr>
                                <td>{{ $token['name'] }}</td>
                                <td>{{ substr((string) $token['created_at'], 0, 16) }}</td>
                                <td>{{ $token['last_used_at'] !== null ? substr((string) $token['last_used_at'], 0, 16) : trans('account.never') }}</td>
                                <td>
                                    <form method="POST" action="{{ url('/account/tokens/' . $token['id']) }}">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-danger btn-small">@lang('account.revoke')</button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        <h3>@lang('account.example')</h3>
        <pre>curl {{ rtrim((string) config('app.url'), '/') }}/api/v1/tickets \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Accept: application/json"</pre>
    </section>

    <aside class="card">
        <form method="POST" action="{{ url('/account/tokens') }}">
            @csrf
            <div class="form-group">
                <label for="name">@lang('account.token_name')</label>
                <input type="text" id="name" name="name" maxlength="100" required aria-invalid="{{ errors('name') ? 'true' : 'false' }}">
                @if(errors('name'))<p class="field-error">{{ errors('name') }}</p>@endif
                <p class="hint">@lang('account.token_name_hint')</p>
            </div>
            <button type="submit" class="btn">@lang('account.create')</button>
        </form>
    </aside>
</div>
@endsection
