@extends('layouts.app', ['title' => __('Closing the period')])

@section('content')
<x-page-header :title="__('Closing the period')" icon="lock" :subtitle="__('Period :number is open now.', ['number' => $number])" />

<form method="POST" action="{{ route('closing.store') }}" class="card mb-3" data-confirm="{{ __('Close the period for the whole business? This cannot be undone.') }}" data-confirm-ok="{{ __('Close the period') }}">
    @csrf
    <div class="card-body">
        <p>{{ __('Every year, or after the reports are cleared with the bank: the profit of each branch moves to previous years or to capital, the books carry on with the same balances, and hawala numbers start from 1 again. Nothing is deleted; the closed period stays to be read and reported for as long as the law asks (five years).') }}</p>
        <table class="table w-100">
            <thead><tr><th>{{ __('Branch') }}</th><th class="text-end">{{ __('Profit or loss of the period (AFN)') }}</th><th>{{ __('Where the profit goes') }}</th></tr></thead>
            <tbody>
                @foreach ($branches as $row)
                    <tr>
                        <td>{{ $row['branch']->name }}</td>
                        <td class="text-end">{{ money($row['profit']) }}</td>
                        <td>
                            <select class="form-select" name="to[{{ $row['branch']->id }}]" @disabled(! $canClose)>
                                <option value="retained">{{ __('Stays as profit of previous years') }}</option>
                                <option value="capital">{{ __('Added to capital') }}</option>
                            </select>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        @if ($canClose)
            <div class="form-check"><input class="form-check-input @error('sure') is-invalid @enderror" type="checkbox" id="c-sure" name="sure" value="1" required><label class="form-check-label" for="c-sure">{{ __('The reports of this period are printed and handed in, and I want to close it.') }}</label></div>
        @endif
    </div>
    @if ($canClose)
        <div class="card-footer text-end"><button type="submit" class="btn btn-danger"><x-icon name="lock" class="me-1" /> {{ __('Close the period') }}</button></div>
    @endif
</form>

<div class="card">
    <div class="card-header"><h2 class="h5 card-title mb-0">{{ __('Closed periods (archive)') }}</h2></div>
    <div class="card-body">
        <table class="table table-hover w-100">
            <thead><tr><th>{{ __('Period') }}</th><th>{{ __('From') }}</th><th>{{ __('Closed on') }}</th><th>{{ __('Branches') }}</th><th>{{ __('User') }}</th><th class="no-print"></th></tr></thead>
            <tbody>
                @forelse ($periods as $period)
                    @php $range = ['from' => $period->started_on?->toDateString() ?? '2000-01-01', 'to' => $period->closed_on->toDateString(), 'branch' => 'all']; @endphp
                    <tr>
                        <td>{{ $period->number }}</td><td>{{ fmt_date($period->started_on) }}</td><td>{{ fmt_date($period->closed_on) }}</td>
                        <td>@foreach ($period->branches as $b)<div class="small">{{ $b['name'] }}: {{ money($b['profit']) }} → {{ $b['to'] === 'capital' ? __('Added to capital') : __('Stays as profit of previous years') }}</div>@endforeach</td>
                        <td>{{ $period->closer?->name }}</td>
                        <td class="text-nowrap no-print"><a href="{{ route('reports.balance', ['to' => $range['to']]) }}">{{ __('Balance Sheet') }}</a> · <a href="{{ route('reports.profit', $range) }}">{{ __('Profit & Loss') }}</a></td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center text-muted py-4">{{ __('Nothing here yet') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
