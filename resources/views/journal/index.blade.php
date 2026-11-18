@extends(request()->boolean('print') ? 'layouts.print' : 'layouts.app', ['title' => __('Roznamcha')])

@php
    $typeName = fn (string $type) => __(ucfirst(str_replace('_', ' ', $type)));
    $chosen = ($f['account'] ?? null) ? $accounts->firstWhere('id', (int) $f['account']) : null;
@endphp

@section('content')
<x-page-header :title="__('Roznamcha')" icon="book" :subtitle="__('The general ledger: every entry of every transaction.')">
    @can('journal.manage')<a class="btn btn-primary" href="{{ route('journal.create') }}"><x-icon name="plus" class="me-1" /> {{ __('Journal entry by hand') }}</a>@endcan
</x-page-header>

@if ($balances)
    <div class="card mb-3">
        <div class="card-header d-flex flex-wrap gap-2 align-items-center justify-content-between">
            <h2 class="h5 card-title mb-0">{{ $chosen ? $chosen->label() : __('Cash and banks') }}</h2>
            @if ($single)
                @if (! $day)
                    <span class="badge bg-secondary-subtle text-secondary">{{ __('The branch did not open on this day') }}</span>
                @elseif ($day->isOpen())
                    <span class="badge bg-success-subtle text-success">{{ __('Day open') }}</span>
                @else
                    <span class="badge bg-secondary-subtle text-secondary">{{ __('Day closed and locked') }}</span>
                @endif
            @endif
        </div>
        <div class="card-body">
            <table class="table w-100 keep-table mb-0">
                <thead><tr><th>{{ __('Currency') }}</th><th class="text-end">{{ $oneDay ? __("Yesterday's balance") : __('Balance before :date', ['date' => fmt_date($from)]) }}</th><th class="text-end">{{ $oneDay ? __("Today's balance") : __('Balance on :date', ['date' => fmt_date($to)]) }}</th><th class="text-end">{{ __('Difference') }}</th></tr></thead>
                <tbody>
                    @foreach ($balances as $row)
                        <tr>
                            <td><strong>{{ $row['currency']->code }}</strong></td>
                            <td class="text-end text-nowrap" dir="ltr">{{ money($row['before']) }}</td>
                            <td class="text-end text-nowrap fw-semibold" dir="ltr">{{ money($row['after']) }}</td>
                            <td class="text-end text-nowrap {{ \App\Support\Num::isNegative($row['difference']) ? 'text-danger' : (\App\Support\Num::isZero($row['difference']) ? '' : 'text-success') }}" dir="ltr">{{ \App\Support\Num::isPositive($row['difference']) ? '+' : '' }}{{ money($row['difference']) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endif

<div class="card">
    <div class="card-body">
        <form method="GET" class="filter-bar row g-2 align-items-end mb-3 no-print">
            @include('partials.branch-filter')
            @include('partials.date-filter')
            <div class="col-12 col-md-3">
                <label class="form-label small mb-1" for="j-account">{{ __('Account') }}</label>
                <select class="form-select" id="j-account" name="account" data-search>
                    <option value="">{{ __('All') }}</option>
                    @foreach ($accounts as $account)
                        <option value="{{ $account->id }}" @selected((int) ($f['account'] ?? 0) === $account->id)>{{ $account->label() }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label small mb-1" for="j-type">{{ __('Transaction type') }}</label>
                <select class="form-select" id="j-type" name="type">
                    <option value="">{{ __('All') }}</option>
                    @foreach ($types as $type)
                        <option value="{{ $type }}" @selected(($f['type'] ?? '') === $type)>{{ $typeName($type) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-md-auto">
                <label class="form-label small mb-1" for="j-currency">{{ __('Currency') }}</label>
                <select class="form-select" id="j-currency" name="currency">
                    <option value="">{{ __('All') }}</option>
                    @foreach ($currencies as $currency)
                        <option value="{{ $currency->id }}" @selected((int) ($f['currency'] ?? 0) === $currency->id)>{{ $currency->code }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label small mb-1" for="j-user">{{ __('User') }}</label>
                <select class="form-select" id="j-user" name="user">
                    <option value="">{{ __('All') }}</option>
                    @foreach ($users as $id => $name)
                        <option value="{{ $id }}" @selected((int) ($f['user'] ?? 0) === $id)>{{ $name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-12 col-md">
                <label class="form-label small mb-1" for="j-q">{{ __('Search') }}</label>
                <input type="search" class="form-control" id="j-q" name="q" value="{{ $term }}" placeholder="{{ __('Number, details, account, amount or user') }}">
            </div>
            <div class="col-auto">
                <div class="form-check mb-2"><input class="form-check-input" type="checkbox" id="j-cancelled" name="cancelled" value="1" @checked(! empty($f['cancelled']))><label class="form-check-label small" for="j-cancelled">{{ __('Show cancelled') }}</label></div>
            </div>
            <div class="col-auto"><button class="btn btn-primary" type="submit">{{ __('Show') }}</button></div>
            <div class="col-auto"><a class="btn btn-light-secondary" href="{{ route('journal.index') }}">{{ __('Clear') }}</a></div>
        </form>

        <table class="table table-hover w-100 keep-table">
            <thead><tr><th>{{ __('Date') }}</th><th>{{ __('No.') }}</th><th>{{ __('Transaction type') }}</th><th>{{ __('Details') }}</th><th>{{ __('Account') }}</th><th class="text-end">{{ __('Debit') }}</th><th class="text-end">{{ __('Credit') }}</th><th>{{ __('Currency') }}</th><th>{{ __('User') }}</th></tr></thead>
            <tbody>
                @forelse ($lines as $line)
                    <tr class="{{ $line->is_void ? 'text-decoration-line-through text-muted' : '' }}">
                        <td class="text-nowrap">{{ fmt_date($line->date) }}</td>
                        <td class="text-nowrap">{{ $line->entry->number }}</td>
                        <td>{{ $typeName($line->entry->type) }}</td>
                        <td>{{ $line->entry->description }}@if ($line->entry->reversed_by_id)<div class="small text-muted">{{ __('Taken back on a later day') }}</div>@endif @if ($line->is_void && $line->entry->void_reason)<div class="small">{{ $line->entry->void_reason }}</div>@endif</td>
                        <td><a href="{{ request()->fullUrlWithQuery(['account' => $line->account_id, 'page' => null]) }}">{{ $line->account->label() }}</a></td>
                        <td class="text-end text-nowrap" dir="ltr">{{ \App\Support\Num::isZero($line->debit) ? '' : money($line->debit) }}</td>
                        <td class="text-end text-nowrap" dir="ltr">{{ \App\Support\Num::isZero($line->credit) ? '' : money($line->credit) }}</td>
                        <td>{{ $line->account->currency->code }}</td>
                        <td>{{ $line->entry->creator?->name }}</td>
                    </tr>
                @empty
                    <tr><td colspan="9" class="text-center text-muted py-4">{{ __('Nothing was recorded on these days.') }}</td></tr>
                @endforelse
            </tbody>
            @if ($lines->total())
                <tfoot><tr class="fw-semibold"><td colspan="5">{{ __('Total') }} ({{ __('Value in AFN') }})</td><td class="text-end text-nowrap" dir="ltr">{{ money($totals['debit']) }}</td><td class="text-end text-nowrap" dir="ltr">{{ money($totals['credit']) }}</td><td>AFN</td><td></td></tr></tfoot>
            @endif
        </table>
        {{ $lines->links('partials.pagination') }}
    </div>
</div>
@endsection
