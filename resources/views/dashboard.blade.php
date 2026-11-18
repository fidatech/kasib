@extends('layouts.app', ['title' => __('Dashboard')])

@section('content')
<x-page-header :title="__('Dashboard')" icon="gauge-high" :subtitle="(setting('company_name') ?: tenant()?->name).' · '.fmt_date(today_iso())" />

@if ($seesMoney)
    <div class="row g-3">
        @foreach ($money as $row)
            <div class="col-6 col-lg-3">
                <div class="card h-100">
                    <div class="card-body">
                        <div class="small text-muted">{{ __('Cash, bank and saraf') }} · {{ $row['currency']->code }}</div>
                        <div class="h4 mb-1 text-nowrap" dir="ltr">{{ money($row['balance']) }}</div>
                        @unless ($row['currency']->is_base)
                            <div class="small text-muted">{{ $row['rate'] === null ? __('No rate yet') : __('Value in :currency', ['currency' => $base]).': '.money($row['base']) }}</div>
                        @endunless
                    </div>
                </div>
            </div>
        @endforeach
        <div class="col-6 col-lg-3">
            <div class="card h-100">
                <div class="card-body">
                    <div class="small text-muted">{{ __('All money, valued in :currency', ['currency' => $base]) }}</div>
                    <div class="h4 mb-1 text-nowrap" dir="ltr">{{ money($worth) }}</div>
                    <a class="small" href="{{ route('treasury.index') }}">{{ __('Currency Position') }}</a>
                </div>
            </div>
        </div>
    </div>
@endif

<div class="row g-3 mt-0">
    @if ($seesMoney)
        <div class="col-12 col-lg-6">
            <div class="card h-100">
                <div class="card-header d-flex align-items-center justify-content-between">
                    <h2 class="h5 card-title mb-0">{{ __('Customers and suppliers') }}</h2>
                    @can('reports.view')<a class="small" href="{{ route('reports.balances') }}">{{ __('Customer & Supplier Balances') }}</a>@endcan
                </div>
                <div class="card-body">
                    @if (! $owed)
                        <p class="text-muted mb-0">{{ __('Nothing here yet') }}</p>
                    @else
                        <table class="table w-100 keep-table mb-0">
                            <thead><tr><th>{{ __('Currency') }}</th><th class="text-end">{{ __('We owe them') }}</th><th class="text-end">{{ __('They owe us') }}</th></tr></thead>
                            <tbody>
                                @foreach ($owed as $code => $row)
                                    <tr><td><strong>{{ $code }}</strong></td><td class="text-end text-nowrap">{{ money($row['we']) }}</td><td class="text-end text-nowrap">{{ money($row['they']) }}</td></tr>
                                @endforeach
                            </tbody>
                        </table>
                    @endif
                </div>
            </div>
        </div>
    @endif
    @if ($seesPapers)
        <div class="col-12 col-lg-6">
            <div class="card h-100">
                <div class="card-header"><h2 class="h5 card-title mb-0">{{ __('Today') }}</h2></div>
                <div class="card-body">
                    <div class="row g-3 text-center">
                        <div class="col-6 col-md-3"><div class="h4 mb-0">{{ $today['in'] }}</div><div class="small text-muted">{{ __('Money In') }}</div></div>
                        <div class="col-6 col-md-3"><div class="h4 mb-0">{{ $today['out'] }}</div><div class="small text-muted">{{ __('Money Out') }}</div></div>
                        <div class="col-6 col-md-3"><div class="h4 mb-0">{{ $today['exchanges'] }}</div><div class="small text-muted">{{ __('Exchanges') }}</div></div>
                        <div class="col-6 col-md-3"><div class="h4 mb-0 text-nowrap" dir="ltr">{{ money($today['profit']) }}</div><div class="small text-muted">{{ __('Exchange profit') }} {{ $base }}</div></div>
                    </div>
                    @if ($due)
                        <div class="alert alert-warning d-flex align-items-center justify-content-between mt-3 mb-0">
                            <span><x-icon name="calendar-days" class="me-1" /> {{ __(':count payments are overdue or fall due within 7 days.', ['count' => $due]) }}</span>
                            @can('reports.view')<a class="btn btn-sm btn-outline-soft" href="{{ route('reports.due') }}">{{ __('Due Payments') }}</a>@endcan
                        </div>
                    @endif
                </div>
            </div>
        </div>
    @endif
</div>

@if ($seesPapers)
    <div class="card mt-3">
        <div class="card-header"><h2 class="h5 card-title mb-0">{{ __('Latest papers') }}</h2></div>
        <div class="card-body">
            @if ($recent->isEmpty())
                <div class="empty-state">
                    <div class="es-icon"><x-icon name="right-left" /></div>
                    <h2 class="h3">{{ __('Nothing here yet') }}</h2>
                    <p>{{ __('Record the first money in, money out or exchange from the row of buttons above.') }}</p>
                </div>
            @else
                <table class="table table-hover w-100">
                    <thead><tr><th>{{ __('No.') }}</th><th>{{ __('Date') }}</th><th>{{ __('Kind') }}</th><th>{{ __('Account') }}</th><th class="text-end">{{ __('Amount') }}</th></tr></thead>
                    <tbody>
                        @foreach ($recent as $paper)
                            <tr class="{{ $paper->isCancelled() ? 'text-decoration-line-through text-muted' : '' }}">
                                <td class="text-nowrap">{{ $paper->number }}</td>
                                <td class="text-nowrap">{{ fmt_date($paper->date) }}</td>
                                <td>{{ __(\App\Models\Voucher::KINDS[$paper->kind][0] ?? $paper->kind) }}</td>
                                <td>{{ $paper->party?->name ?? $paper->account?->label() }}</td>
                                <td class="text-end text-nowrap">{{ money($paper->amount, $paper->currency?->code) }}@if ($paper->other_amount) → {{ money($paper->other_amount, $paper->otherCurrency?->code) }}@endif</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    </div>
@endif
@endsection
