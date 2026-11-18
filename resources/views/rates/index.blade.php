@extends('layouts.app', ['title' => __('Exchange Rates')])

@section('content')
<x-page-header :title="__('Exchange Rates')" icon="coins" :subtitle="__('Saving new rates values the foreign money of the business again.')" />

<form method="POST" action="{{ route('rates.update') }}" class="card">
    @csrf
    @method('PUT')
    <div class="card-body">
        @include('rates.form')
        <div class="form-text">{{ __('A rate between two foreign currencies is worked out from their AFN rates.') }}</div>
    </div>
    @if ($canManage)
        <div class="card-footer d-flex gap-2 justify-content-end">
            <button type="submit" class="btn btn-primary"><x-icon name="check" class="me-1" /> {{ __('Save') }}</button>
        </div>
    @endif
</form>

<div class="card mt-3">
    <div class="card-header"><h2 class="h5 card-title mb-0">{{ __('Last changes') }}</h2></div>
    <div class="card-body">
        <table class="table table-hover w-100">
            <thead><tr><th>{{ __('When') }}</th><th>{{ __('Currency') }}</th><th class="text-end">{{ __('Rate in AFN') }}</th></tr></thead>
            <tbody>
                @forelse ($history as $rate)
                    <tr>
                        <td class="text-nowrap">{{ fmt_date($rate->created_at) }} {{ $rate->created_at->format('H:i') }}</td>
                        <td>{{ $rate->currency->code }}</td>
                        <td class="text-end">{{ \App\Support\Num::format($rate->rate, 6, true) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-center text-muted py-4">{{ __('Nothing here yet') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
