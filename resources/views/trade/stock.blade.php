@extends('layouts.app', ['title' => __('Stock card')])

@section('content')
<x-page-header :title="$item->name" icon="boxes-stacked" :crumbs="[[__('Items'), route('items.index')], [__('Stock card')]]" :subtitle="trim(($item->code ? $item->code.' · ' : '').__('In stock').': '.qty($item->stock).' '.$item->unit.' · '.__('Average cost').': '.money($item->avg_cost, $base))" />

@if ($canManage)
    <form method="POST" action="{{ route('items.count', ['id' => $item->id]) }}" class="card mb-3">
        @csrf
        <div class="card-header"><h2 class="h5 mb-0">{{ __('Stock count') }}</h2></div>
        <div class="card-body">
            <div class="row g-3 align-items-end">
                <div class="col-6 col-md-3">
                    <label class="form-label" for="s-counted">{{ __('Counted now') }} <span class="req">*</span></label>
                    <input type="text" inputmode="decimal" dir="ltr" class="form-control @error('counted') is-invalid @enderror" id="s-counted" name="counted" value="{{ old('counted') }}" required>
                </div>
                <div class="col-6 col-md-3">
                    <label class="form-label" for="s-cost">{{ __('Cost of one, :currency', ['currency' => $base]) }}</label>
                    <input type="text" inputmode="decimal" dir="ltr" class="form-control" id="s-cost" name="unit_cost" value="{{ old('unit_cost') }}" placeholder="{{ money($item->avg_cost + 0 ? $item->avg_cost : $item->cost_price) }}">
                </div>
                <div class="col-12 col-md-4">
                    <label class="form-label" for="s-note">{{ __('Note') }}</label>
                    <input type="text" class="form-control" id="s-note" name="note" value="{{ old('note') }}" maxlength="255">
                </div>
                <div class="col-auto"><button type="submit" class="btn btn-primary"><x-icon name="check" class="me-1" /> {{ __('Save') }}</button></div>
                <div class="col-12"><div class="form-text">{{ __('Use it for the stock you start with, and when a count differs from the books. More than the books enters at the cost written; less leaves as cost of goods.') }}</div></div>
            </div>
        </div>
    </form>
@endif

<div class="card">
    <div class="card-header"><h2 class="h5 mb-0">{{ __('Invoices of this item') }}</h2></div>
    <div class="card-body">
        @if ($lines->isEmpty())
            <div class="empty-state"><div class="es-icon"><x-icon name="boxes-stacked" /></div><h2 class="h3">{{ __('Nothing here yet') }}</h2></div>
        @else
            <table class="table table-hover w-100 keep-table">
                <thead><tr><th>{{ __('Date') }}</th><th>{{ __('No.') }}</th><th>{{ __('What') }}</th><th>{{ __('Account') }}</th><th class="text-end">{{ __('In') }}</th><th class="text-end">{{ __('Out') }}</th><th class="text-end">{{ __('Price') }}</th></tr></thead>
                <tbody>
                    @foreach ($lines as $line)
                        @php $out = $line->invoice->goodsLeave(); @endphp
                        <tr>
                            <td class="text-nowrap">{{ fmt_date($line->invoice->date) }}</td>
                            <td class="text-nowrap"><a href="{{ route('trade.show', ['kind' => $line->invoice->slug(), 'id' => $line->invoice->id]) }}">{{ $line->invoice->number }}</a></td>
                            <td>{{ __(\App\Models\Invoice::KINDS[$line->invoice->kind][0]) }}</td>
                            <td>{{ $line->invoice->party?->is_system ? __($line->invoice->party->name) : $line->invoice->party?->name }}</td>
                            <td class="text-end text-nowrap">{{ $out ? '' : qty($line->qty) }}</td>
                            <td class="text-end text-nowrap">{{ $out ? qty($line->qty) : '' }}</td>
                            <td class="text-end text-nowrap">{{ money($line->price) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            {{ $lines->links() }}
        @endif
    </div>
</div>
@endsection
