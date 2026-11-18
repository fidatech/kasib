@extends('layouts.app', ['title' => __('Balance Confirmations')])

@section('content')
<x-page-header :title="__('Balance Confirmations')" icon="circle-check" :subtitle="__('Customers and suppliers are sent their balance on WhatsApp and asked to confirm it.')">
    @if ($canManage)
        <form method="POST" action="{{ route('confirmations.send') }}" class="d-inline" data-confirm="{{ __('Send the balance to everybody who was not asked this month?') }}" data-confirm-ok="{{ __('Send') }}">
            @csrf
            <button type="submit" class="btn btn-primary"><x-icon name="paper-plane" class="me-1" /> {{ __('Send the balances now') }}</button>
        </form>
    @endif
</x-page-header>

<div class="card">
    <div class="card-body">
        <form method="GET" class="row g-2 align-items-end mb-3 no-print">
            <div class="col-6 col-md-auto">
                <label class="form-label small mb-1" for="c-status">{{ __('Status') }}</label>
                <select class="form-select" id="c-status" name="status">
                    <option value="">{{ __('All') }}</option>
                    @foreach ($statuses as $key => $label)
                        <option value="{{ $key }}" @selected(request('status') === $key)>{{ __($label) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-auto"><button class="btn btn-light-secondary" type="submit">{{ __('Show') }}</button></div>
        </form>

        @if ($rows->isEmpty())
            <div class="empty-state">
                <div class="es-icon"><x-icon name="circle-check" /></div>
                <h2 class="h3">{{ __('Nothing here yet') }}</h2>
            </div>
        @else
            <table class="table table-hover w-100 keep-table">
                <thead><tr><th>{{ __('Sent on') }}</th><th>{{ __('Account') }}</th><th>{{ __('Phone') }}</th><th>{{ __('Balance sent') }}</th><th>{{ __('Status') }}</th><th>{{ __('Note') }}</th>@if ($canManage)<th class="text-end no-print">{{ __('The answer') }}</th>@endif</tr></thead>
                <tbody>
                    @foreach ($rows as $row)
                        <tr>
                            <td class="text-nowrap">{{ fmt_date($row->sent_at) }}</td>
                            <td>@if ($row->party?->group)<a href="{{ route('accounts.statement', ['group' => $row->party->group->key, 'id' => $row->party->id]) }}">{{ $row->party->name }}</a>@endif</td>
                            <td dir="ltr">{{ $row->party?->phone }}</td>
                            <td>{{ $row->balance }}</td>
                            <td><span class="badge {{ ['sent' => 'bg-secondary-subtle text-secondary', 'confirmed' => 'bg-success-subtle text-success', 'disputed' => 'bg-danger-subtle text-danger'][$row->status] ?? '' }}">{{ __($statuses[$row->status] ?? $row->status) }}</span></td>
                            <td>{{ $row->note }}</td>
                            @if ($canManage)
                                <td class="text-end no-print">
                                    <form method="POST" action="{{ route('confirmations.answer', ['id' => $row->id]) }}" class="d-inline-flex gap-1">
                                        @csrf
                                        <input type="text" class="form-control form-control-sm" name="note" value="{{ $row->note }}" placeholder="{{ __('Note') }}" maxlength="255" style="width: 9rem">
                                        <button type="submit" name="status" value="confirmed" class="btn btn-sm btn-light-secondary">{{ __('Confirmed') }}</button>
                                        <button type="submit" name="status" value="disputed" class="btn btn-sm btn-light-secondary">{{ __('Not agreed') }}</button>
                                    </form>
                                </td>
                            @endif
                        </tr>
                    @endforeach
                </tbody>
            </table>
            {{ $rows->links() }}
        @endif
    </div>
</div>
@endsection
