@extends('layouts.app')
@section('title', trans('admin.users_title'))

@section('content')
<div class="page-head">
    <div>
        <h1>@lang('admin.users_title')</h1>
        <p class="lead">@lang('admin.users_lead')</p>
    </div>
</div>

<section class="card">
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>@lang('admin.col_name')</th>
                    <th>@lang('admin.col_email')</th>
                    <th>@lang('admin.col_joined')</th>
                    <th>@lang('admin.col_role')</th>
                </tr>
            </thead>
            <tbody>
                @foreach($users as $user)
                    <tr>
                        <td>{{ $user['name'] }} @if((int) $user['id'] === (int) $authUser->id)<span class="badge">@lang('admin.you')</span>@endif</td>
                        <td>{{ $user['email'] }}</td>
                        <td>{{ substr((string) $user['created_at'], 0, 10) }}</td>
                        <td>
                            @if((int) $user['id'] === (int) $authUser->id)
                                {{ trans('app.role.' . ($user['role'] ?? 'none')) }}
                            @else
                                <form method="POST" action="{{ url('/admin/users/' . $user['id']) }}" class="inline-form">
                                    @csrf
                                    @method('PATCH')
                                    <label class="visually-hidden" for="role-{{ $user['id'] }}">@lang('admin.change_role')</label>
                                    <select id="role-{{ $user['id'] }}" name="role">
                                        @foreach($roles as $role)
                                            <option value="{{ $role }}" @selected($user['role'] === $role)>{{ trans('app.role.' . $role) }}</option>
                                        @endforeach
                                    </select>
                                    <button type="submit" class="btn btn-secondary btn-small">@lang('app.save')</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</section>
@endsection
