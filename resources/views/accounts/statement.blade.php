@extends(request()->boolean('print') ? 'layouts.print' : 'layouts.app', ['title' => __('Statement')])

@section('content')
<x-page-header :title="$party->name" :icon="$group->icon" :crumbs="[[$group->label(), route('accounts.index', ['group' => $group->key])], [__('Statement')]]" :subtitle="trim($group->label().' · '.$account->currency->code.($party->phone ? ' · '.$party->phone : ''))">
    <a class="btn btn-outline-soft" href="{{ request()->fullUrlWithQuery(['export' => 'xlsx']) }}"><x-icon name="file-excel" class="me-1" /> {{ __('Excel') }}</a>
    <a class="btn btn-outline-soft" href="{{ request()->fullUrlWithQuery(['print' => 1]) }}" target="_blank" rel="noopener"><x-icon name="print" class="me-1" /> {{ __('Print or PDF') }}</a>
</x-page-header>

<div class="card">
    <div class="card-body">
        <form method="GET" class="row g-2 align-items-end mb-3 no-print">
            <div class="col-6 col-md-auto">
                <label class="form-label small mb-1" for="st-currency">{{ __('Currency') }}</label>
                <select class="form-select" id="st-currency" name="currency">
                    @foreach ($accounts as $one)
                        <option value="{{ $one->currency_id }}" @selected($one->id === $account->id)>{{ $one->currency->code }}</option>
                    @endforeach
                </select>
            </div>
            @include('partials.date-filter')
            <div class="col-auto"><button class="btn btn-light-secondary" type="submit">{{ __('Show') }}</button></div>
        </form>

        <table class="table table-hover w-100 keep-table">
            <thead>
                <tr>
                    <th>{{ __('Date') }}</th><th>{{ __('No.') }}</th><th>{{ __('Details') }}</th>
                    <th class="text-end">{{ $group->isMoney() ? __('In') : __('Paid to them') }}</th>
                    <th class="text-end">{{ $group->isMoney() ? __('Out') : __('Received from them') }}</th>
                    <th class="text-end">{{ $group->isMoney() ? __('Balance') : __('We owe them') }}</th>
                </tr>
            </thead>
            <tbody>
                <tr class="fw-semibold"><td colspan="5">{{ __('Balance before :date', ['date' => fmt_date($from)]) }}</td><td class="text-end text-nowrap">{{ money($opening, $account->currency->code) }}</td></tr>
                @foreach ($rows as $row)
                    <tr>
                        <td class="text-nowrap">{{ fmt_date($row['line']->date) }}</td>
                        <td class="text-nowrap">{{ $row['line']->entry->number }}</td>
                        <td>{{ $row['line']->memo ?: $row['line']->entry->description }}</td>
                        <td class="text-end text-nowrap">{{ \App\Support\Num::isZero($row['line']->debit) ? '' : money($row['line']->debit) }}</td>
                        <td class="text-end text-nowrap">{{ \App\Support\Num::isZero($row['line']->credit) ? '' : money($row['line']->credit) }}</td>
                        <td class="text-end text-nowrap">{{ money($row['balance']) }}</td>
                    </tr>
                @endforeach
                <tr class="fw-semibold"><td colspan="5">{{ __('Balance on :date', ['date' => fmt_date($to)]) }}</td><td class="text-end text-nowrap">{{ money($closing, $account->currency->code) }}</td></tr>
            </tbody>
        </table>
        @unless ($group->isMoney())
            <div class="form-text">{{ __('A balance below zero means they owe the business.') }}</div>
        @endunless
    </div>
</div>
@endsection
