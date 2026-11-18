@extends('layouts.app', ['title' => __('Dashboard')])

@section('content')
<x-page-header :title="__('Dashboard')" icon="gauge-high" :subtitle="(setting('company_name') ?: tenant()?->name).' · '.fmt_date(today_iso())" />

@if ($needsSetup)
    <div class="alert alert-info d-flex flex-wrap gap-2 align-items-center justify-content-between">
        <span><x-icon name="wand-magic-sparkles" class="me-1" /> {{ __('Welcome. Three choices and the system is set up for your kind of business.') }}</span>
        <a class="btn btn-sm btn-primary" href="{{ route('setup.show') }}">{{ __('First Setup') }}</a>
    </div>
@endif
@if ($reminders)
    <div class="alert alert-warning d-flex flex-wrap gap-2 align-items-center justify-content-between">
        <span><x-icon name="bell" class="me-1" /> {{ __(':count reminders are due.', ['count' => $reminders]) }}</span>
        <a class="btn btn-sm btn-outline-soft" href="{{ route('reminders.index') }}">{{ __('Reminders') }}</a>
    </div>
@endif
@php
    $kindTone = ['money_in' => 'in', 'money_out' => 'out', 'transfer' => 'move', 'exchange' => 'swap', 'income' => 'in', 'deal_cost' => 'out', 'deal_receipt' => 'in'];
    $trend = function (?float $change, bool $goodWhenUp = true) {
        if ($change === null) { return ''; }
        $up = $change >= 0;
        return '<span class="dash-trend '.(($up === $goodWhenUp) ? 'is-good' : 'is-bad').'" dir="ltr">'.($up ? '▲' : '▼').' '.number_format(abs($change), 1).'%</span>';
    };
@endphp
@if ($seesMoney)
    <div class="row g-3">
        <div class="col-12 col-sm-6 col-xl-3">
            <a class="kpi kpi-blue" href="{{ route('treasury.index') }}"><span class="kpi-icon"><x-icon name="vault" /></span><div class="min-w-0"><div class="kpi-label">{{ __('Cash, bank and saraf') }}</div><div class="kpi-value text-nowrap" dir="ltr">{{ money($worth) }}</div><div class="kpi-note">{{ __('All money, valued in :currency', ['currency' => $base]) }}</div></div></a>
        </div>
        @if ($insight)
            <div class="col-12 col-sm-6 col-xl-3">
                <a class="kpi kpi-green" href="{{ route('reports.balances') }}"><span class="kpi-icon"><x-icon name="hand-holding-dollar" /></span><div class="min-w-0"><div class="kpi-label">{{ __('They owe us') }}</div><div class="kpi-value text-nowrap amt-in" dir="ltr">{{ money($insight['parties']['owe_total']) }}</div><div class="kpi-note">{{ __(':count accounts', ['count' => $insight['parties']['owe_count']]) }} · {{ $base }}</div></div></a>
            </div>
            <div class="col-12 col-sm-6 col-xl-3">
                <a class="kpi kpi-red" href="{{ route('reports.balances') }}"><span class="kpi-icon"><x-icon name="scale-unbalanced" /></span><div class="min-w-0"><div class="kpi-label">{{ __('We owe them') }}</div><div class="kpi-value text-nowrap amt-out" dir="ltr">{{ money($insight['parties']['owed_total']) }}</div><div class="kpi-note">{{ __(':count accounts', ['count' => $insight['parties']['owed_count']]) }} · {{ $base }}</div></div></a>
            </div>
            <div class="col-12 col-sm-6 col-xl-3">
                <a class="kpi kpi-violet" href="{{ route('reports.profit') }}"><span class="kpi-icon"><x-icon name="chart-line" /></span><div class="min-w-0"><div class="kpi-label">{{ __('Profit this month') }}</div><div class="kpi-value text-nowrap {{ \App\Support\Num::isNegative($insight['month']['profit']) ? 'amt-out' : 'amt-in' }}" dir="ltr">{{ money($insight['month']['profit']) }}</div><div class="kpi-note">{!! $trend($insight['changes']['profit']) !!} {{ __('against last month') }}</div></div></a>
            </div>
        @endif
    </div>

    <div class="card mt-3">
        <div class="card-header d-flex align-items-center justify-content-between"><h2 class="h5 card-title mb-0">{{ __('Money by currency') }}</h2><a class="small" href="{{ route('treasury.index') }}">{{ __('Currency Position') }}</a></div>
        <div class="card-body">
            <div class="dash-currencies">
                @foreach ($money as $row)
                    <div class="dash-currency">
                        <span class="dash-code">{{ $row['currency']->code }}</span>
                        <div class="min-w-0">
                            <div class="dash-figure text-nowrap {{ \App\Support\Num::isNegative($row['balance']) ? 'amt-out' : '' }}" dir="ltr">{{ money($row['balance']) }}</div>
                            <div class="small text-muted text-nowrap">@if ($row['currency']->is_base){{ __('Base currency') }}@elseif ($row['rate'] === null){{ __('No rate yet') }}@else<span dir="ltr">≈ {{ money($row['base']) }} {{ $base }}</span>@endif</div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
@endif

@if ($insight)
    <div class="row g-3 mt-0">
        <div class="col-12 col-xl-7">
            <div class="card h-100">
                <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-2">
                    <h2 class="h5 card-title mb-0">{{ __('Money in and out, last 14 days') }}</h2>
                    <div class="dash-legend"><span class="is-in">{{ __('In') }}</span><span class="is-out">{{ __('Out') }}</span><span class="text-muted">{{ $base }}</span></div>
                </div>
                <div class="card-body">
                    <div class="dash-bars" role="img" aria-label="{{ __('Money in and out, last 14 days') }}">
                        @foreach ($insight['flow'] as $day)
                            <div class="dash-day" title="{{ fmt_date($day['date']) }} · {{ __('In') }} {{ money($day['in']) }} · {{ __('Out') }} {{ money($day['out']) }}">
                                <div class="dash-pair">
                                    <span class="dash-bar is-in" style="height: {{ round((float) $day['in'] / $insight['top_flow'] * 100, 1) }}%"></span>
                                    <span class="dash-bar is-out" style="height: {{ round((float) $day['out'] / $insight['top_flow'] * 100, 1) }}%"></span>
                                </div>
                                <span class="dash-tick">{{ (int) substr($day['date'], 8, 2) }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
        <div class="col-12 col-xl-5">
            <div class="card h-100">
                <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-2">
                    <h2 class="h5 card-title mb-0">{{ __('Income and expenses, six months') }}</h2>
                    <div class="dash-legend"><span class="is-in">{{ __('Income') }}</span><span class="is-out">{{ __('Expenses') }}</span></div>
                </div>
                <div class="card-body">
                    <div class="dash-bars is-months" role="img" aria-label="{{ __('Income and expenses, six months') }}">
                        @foreach ($insight['months'] as $month)
                            <div class="dash-day" title="{{ $month['month'] }} · {{ __('Income') }} {{ money($month['income']) }} · {{ __('Expenses') }} {{ money($month['expense']) }}">
                                <div class="dash-pair">
                                    <span class="dash-bar is-in" style="height: {{ round(max(0, (float) $month['income']) / $insight['top_month'] * 100, 1) }}%"></span>
                                    <span class="dash-bar is-out" style="height: {{ round(max(0, (float) $month['expense']) / $insight['top_month'] * 100, 1) }}%"></span>
                                </div>
                                <span class="dash-tick" dir="ltr">{{ substr($month['month'], 5, 2) }}/{{ substr($month['month'], 2, 2) }}</span>
                            </div>
                        @endforeach
                    </div>
                    <div class="dash-split">
                        <div><span>{{ __('Income this month') }}</span><strong class="amt-in" dir="ltr">{{ money($insight['month']['income']) }}</strong>{!! $trend($insight['changes']['income']) !!}</div>
                        <div><span>{{ __('Expenses this month') }}</span><strong class="amt-out" dir="ltr">{{ money($insight['month']['expense']) }}</strong>{!! $trend($insight['changes']['expense'], false) !!}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3 mt-0">
        @foreach ([['owe', 'Who owes us most', 'amt-in'], ['owed', 'Whom we owe most', 'amt-out']] as [$side, $heading, $tone])
            <div class="col-12 col-lg-6">
                <div class="card h-100">
                    <div class="card-header d-flex align-items-center justify-content-between"><h2 class="h5 card-title mb-0">{{ __($heading) }}</h2>@can('reports.view')<a class="small" href="{{ route('reports.balances') }}">{{ __('See all') }}</a>@endcan</div>
                    <div class="card-body">
                        @forelse ($insight['parties'][$side] as $row)
                            @php($top = (float) $insight['parties'][$side][0]['amount'] ?: 1)
                            <a class="dash-rank" href="{{ route('accounts.statement', ['group' => $row['section'], 'id' => $row['id']]) }}">
                                <span class="dash-rank-name">{{ $row['name'] }}</span>
                                <span class="dash-rank-amount {{ $tone }}" dir="ltr">{{ money($row['amount']) }}</span>
                                <span class="dash-rank-track"><span class="{{ $tone }}" style="width: {{ round((float) $row['amount'] / $top * 100, 1) }}%"></span></span>
                            </a>
                        @empty
                            <p class="text-muted mb-0">{{ __('Nothing here yet') }}</p>
                        @endforelse
                    </div>
                </div>
            </div>
        @endforeach
    </div>
@endif

@if ($seesPapers)
    <div class="row g-3 mt-0">
        <div class="col-6 col-lg-3"><a class="dash-today is-in" href="{{ route('money-in.index') }}"><x-icon name="arrow-down" /><div><strong>{{ $today['in'] }}</strong><span>{{ __('Money In') }} · {{ __('Today') }}</span></div></a></div>
        <div class="col-6 col-lg-3"><a class="dash-today is-out" href="{{ route('money-out.index') }}"><x-icon name="arrow-up" /><div><strong>{{ $today['out'] }}</strong><span>{{ __('Money Out') }} · {{ __('Today') }}</span></div></a></div>
        <div class="col-6 col-lg-3"><a class="dash-today is-swap" href="{{ route('exchanges.index') }}"><x-icon name="right-left" /><div><strong>{{ $today['exchanges'] }}</strong><span>{{ __('Exchanges') }} · {{ __('Today') }}</span></div></a></div>
        <div class="col-6 col-lg-3"><div class="dash-today is-move"><x-icon name="coins" /><div><strong class="text-nowrap" dir="ltr">{{ money($today['profit']) }}</strong><span>{{ __('Exchange profit') }} {{ $base }}</span></div></div></div>
    </div>
    @if ($due || ($insight['low_stock'] ?? 0))
        <div class="row g-3 mt-0">
            @if ($due)
                <div class="col-12 col-lg">
                    <div class="alert alert-warning d-flex align-items-center justify-content-between mb-0 h-100">
                        <span><x-icon name="calendar-days" class="me-1" /> {{ __(':count payments are overdue or fall due within 7 days.', ['count' => $due]) }}</span>
                        @can('reports.view')<a class="btn btn-sm btn-outline-soft" href="{{ route('reports.due') }}">{{ __('Due Payments') }}</a>@endcan
                    </div>
                </div>
            @endif
            @if ($insight['low_stock'] ?? 0)
                <div class="col-12 col-lg">
                    <div class="alert alert-warning d-flex align-items-center justify-content-between mb-0 h-100">
                        <span><x-icon name="boxes-stacked" class="me-1" /> {{ __(':count items are low in stock.', ['count' => $insight['low_stock']]) }}</span>
                        <a class="btn btn-sm btn-outline-soft" href="{{ route('items.index', ['show' => 'low']) }}">{{ __('Items') }}</a>
                    </div>
                </div>
            @endif
        </div>
    @endif
@endif

@if ($seesPapers)
    <div class="card mt-3">
        <div class="card-header d-flex align-items-center justify-content-between"><h2 class="h5 card-title mb-0">{{ __('Latest papers') }}</h2>@can('journal.view')<a class="small" href="{{ route('journal.index') }}">{{ __('Roznamcha') }}</a>@endcan</div>
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
                                <td><span class="kind-badge is-{{ $kindTone[$paper->kind] ?? 'move' }}">{{ __(\App\Models\Voucher::KINDS[$paper->kind][0] ?? $paper->kind) }}</span></td>
                                <td>{{ $paper->party?->name ?? $paper->account?->label() }}</td>
                                <td class="text-end text-nowrap fw-semibold {{ ['in' => 'amt-in', 'out' => 'amt-out'][$kindTone[$paper->kind] ?? ''] ?? '' }}">{{ money($paper->amount, $paper->currency?->code) }}@if ($paper->other_amount) → {{ money($paper->other_amount, $paper->otherCurrency?->code) }}@endif</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    </div>
@endif
@endsection
