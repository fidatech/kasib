@extends('layouts.auth', ['title' => __('Statement')])

{{-- The customer opens this page from his private link, without signing in. It only shows; nothing can be changed. --}}
@section('content')
<div class="text-center mb-3">
    <h1 class="h4 mb-1">{{ $business }}</h1>
    <div class="text-muted">{{ __('Statement of :name', ['name' => $party->name]) }} · {{ fmt_date(today_iso()) }}</div>
</div>
@forelse ($accounts as $account)
    <div class="alert {{ \App\Support\Num::isNegative($account['balance']) ? 'alert-warning' : 'alert-success' }} d-flex justify-content-between mb-2">
        <span>{{ \App\Support\Num::isNegative($account['balance']) ? __('You owe us') : __('We owe you') }}</span>
        <strong dir="ltr">{{ money(\App\Support\Num::abs($account['balance']), $account['currency']) }}</strong>
    </div>
    @if ($account['lines']->isNotEmpty())
        <div class="table-responsive mb-4">
            <table class="table table-sm w-100 keep-table">
                <thead><tr><th>{{ __('Date') }}</th><th class="text-end">{{ __('You gave') }}</th><th class="text-end">{{ __('You received') }}</th></tr></thead>
                <tbody>
                    @foreach ($account['lines'] as $line)
                        <tr>
                            <td class="text-nowrap">{{ fmt_date($line->date) }}</td>
                            <td class="text-end text-nowrap" dir="ltr">{{ \App\Support\Num::isZero($line->credit) ? '' : money($line->credit) }}</td>
                            <td class="text-end text-nowrap" dir="ltr">{{ \App\Support\Num::isZero($line->debit) ? '' : money($line->debit) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
@empty
    <div class="alert alert-info">{{ __('There is nothing on this account.') }}</div>
@endforelse
<p class="small text-muted text-center mb-0">{{ __('The lines since :date. If something is different from your own notes, tell us.', ['date' => fmt_date($since)]) }}</p>
@endsection
