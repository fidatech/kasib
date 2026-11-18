@extends('layouts.app', ['title' => __('Active Sessions')])

@section('content')
<x-page-header :title="__('Active Sessions')" icon="display" :subtitle="$all ? __('The browsers every user is signed in from.') : __('The browsers you are signed in from.')">
    <form method="POST" action="{{ route('sessions.others') }}" class="d-inline" data-confirm="{{ __('Sign out every other browser of your user?') }}" data-confirm-ok="{{ __('Sign them out') }}">
        @csrf
        <button type="submit" class="btn btn-outline-soft">{{ __('Sign out my other browsers') }}</button>
    </form>
</x-page-header>

<div class="card">
    <div class="card-body">
        <table class="table table-hover w-100" data-rows>
            <thead><tr>@if ($all)<th>{{ __('User') }}</th>@endif<th>{{ __('Browser') }}</th><th>{{ __('Address (IP)') }}</th><th>{{ __('Signed in') }}</th><th>{{ __('Last seen') }}</th><th class="text-end">{{ __('Actions') }}</th></tr></thead>
            <tbody>
                @foreach ($sessions as $session)
                    <tr>
                        @if ($all)<td>{{ $session->user?->name }}</td>@endif
                        <td>{{ $session->device() }} @if ($session->token === $current)<span class="badge bg-success-subtle text-success">{{ __('This browser') }}</span>@endif</td>
                        <td dir="ltr">{{ $session->ip }}</td>
                        <td class="text-nowrap">{{ fmt_date($session->created_at) }} {{ $session->created_at?->timezone(config('app.timezone'))->format('H:i') }}</td>
                        <td class="text-nowrap">{{ fmt_date($session->last_seen_at) }} {{ $session->last_seen_at?->timezone(config('app.timezone'))->format('H:i') }}</td>
                        <td class="text-end">
                            @if ($session->token !== $current)
                                <form method="POST" action="{{ route('sessions.destroy', ['id' => $session->id]) }}" class="d-inline">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-light-secondary">{{ __('Sign out') }}</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endsection
