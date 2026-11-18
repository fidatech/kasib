@extends('layouts.central', ['title' => __('Payment requests')])

@section('content')
<x-page-header :title="__('Payment requests')" icon="wallet" :subtitle="__('What the businesses say they paid through HesabPay. Check your HesabPay account, then approve or reject.')" />

<div class="card">
    <div class="card-header">
        <div class="seg">
            @foreach (\App\Models\Central\PaymentRequest::STATUSES as $key => $label)
                <a href="{{ route('admin.payment-requests.index', ['status' => $key]) }}" class="{{ $status === $key ? 'active' : '' }}">{{ __($label) }}@if (($counts[$key] ?? 0) > 0) <span class="badge bg-secondary-subtle text-secondary">{{ $counts[$key] }}</span>@endif</a>
            @endforeach
        </div>
    </div>
    <div class="card-body">
        <table class="table table-hover w-100">
            <thead><tr><th>{{ __('Date') }}</th><th>{{ __('Business') }}</th><th class="text-end">{{ __('Amount') }}</th><th>{{ __('Transaction number') }}</th><th>{{ __('Picture') }}</th><th>{{ __('Invoice') }}</th><th class="text-end no-print">{{ __('Actions') }}</th></tr></thead>
            <tbody>
                @forelse ($requests as $row)
                    <tr>
                        <td class="text-nowrap">{{ $row->created_at?->toDateString() }}</td>
                        <td>{{ $row->tenant?->name }}<div class="small text-muted" dir="ltr">{{ $row->tenant?->domain }}</div>@if ($row->sender)<div class="small text-muted">{{ $row->sender }}</div>@endif</td>
                        <td class="text-end" dir="ltr">${{ money($row->amount) }}</td>
                        <td dir="ltr">{{ $row->reference }}</td>
                        <td>@if ($row->screenshot_path)<a href="{{ route('admin.payment-requests.picture', ['id' => $row->id]) }}" target="_blank" rel="noopener">{{ __('Open') }}</a>@else — @endif</td>
                        <td>{{ $row->invoice?->number ?: __('A new one for the next year') }}@if ($row->invoice)<div class="small text-muted" dir="ltr">${{ money($row->invoice->total) }}</div>@endif</td>
                        <td class="text-end no-print">
                            @if ($row->status === 'pending' && auth('admin')->user()?->allows('billing.manage'))
                                <form method="POST" action="{{ route('admin.payment-requests.approve', ['id' => $row->id]) }}" class="d-inline-flex gap-1 mb-1">
                                    @csrf
                                    <input type="text" inputmode="decimal" dir="ltr" class="form-control form-control-sm" name="amount" value="{{ $row->amount }}" style="width: 6.5rem" aria-label="{{ __('Amount') }}">
                                    <button type="submit" class="btn btn-sm btn-primary">{{ __('Approve') }}</button>
                                </form>
                                <form method="POST" action="{{ route('admin.payment-requests.reject', ['id' => $row->id]) }}" class="d-inline-flex gap-1">
                                    @csrf
                                    <select class="form-select form-select-sm" name="reason" required style="width: 12rem" aria-label="{{ __('Reason') }}">
                                        <option value="">{{ __('Reason') }}</option>
                                        @foreach (\App\Models\Central\PaymentRequest::REASONS as $key => $label)
                                            <option value="{{ $key }}">{{ __($label) }}</option>
                                        @endforeach
                                    </select>
                                    <button type="submit" class="btn btn-sm btn-light-secondary">{{ __('Reject') }}</button>
                                </form>
                            @elseif ($row->status === 'approved')
                                {{ $row->payment?->number }}
                            @elseif ($row->status === 'rejected')
                                <span class="small text-muted">{{ __(\App\Models\Central\PaymentRequest::REASONS[$row->reason] ?? (string) $row->reason) }}</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center text-muted py-4">{{ __('Nothing here yet') }}</td></tr>
                @endforelse
            </tbody>
        </table>
        {{ $requests->links() }}
    </div>
</div>
@endsection
