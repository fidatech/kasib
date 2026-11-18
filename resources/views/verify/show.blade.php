@extends('layouts.auth', ['title' => __('Receipt check')])

{{-- Anyone who holds a receipt can open this page from the address printed on it, without signing in. --}}
@section('content')
<div class="text-center mb-3">
    <h1 class="h4 mb-1">{{ $business }}</h1>
    <div class="text-muted">{{ __('Receipt check') }}</div>
</div>
@if (! $voucher)
    <div class="alert alert-danger">{{ __('This receipt is not in the books of :name. Do not accept it.', ['name' => $business]) }}</div>
@else
    <div class="alert {{ $voucher->isCancelled() ? 'alert-danger' : 'alert-success' }}">{{ $voucher->isCancelled() ? __('This receipt was cancelled.') : __('This receipt is real and unchanged.') }}</div>
    <table class="table w-100 keep-table">
        <tbody>
            <tr><th>{{ __('No.') }}</th><td dir="ltr">{{ $voucher->number }}</td></tr>
            <tr><th>{{ __('Kind') }}</th><td>{{ __(\App\Models\Voucher::KINDS[$voucher->kind][0] ?? $voucher->kind) }}</td></tr>
            <tr><th>{{ __('Date') }}</th><td>{{ fmt_date($voucher->date) }}</td></tr>
            <tr><th>{{ __('Amount') }}</th><td dir="ltr">{{ money($voucher->amount, $voucher->currency->code) }}@if ($voucher->other_amount) → {{ money($voucher->other_amount, $voucher->otherCurrency?->code) }}@endif</td></tr>
            @if ($voucher->party)<tr><th>{{ __('Account') }}</th><td>{{ $voucher->party->name }}</td></tr>@endif
        </tbody>
    </table>
@endif
@endsection
