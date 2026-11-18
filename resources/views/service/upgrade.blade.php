@extends('layouts.app', ['title' => __('The Pro plan')])

@section('content')
<x-page-header :title="__('This page is part of the Pro plan')" icon="file-invoice-dollar" :subtitle="__('Your business is on the Starter plan.')" />

<div class="card">
    <div class="card-body">
        <p>{{ __('The Pro plan adds sales and purchases to the accounts you already keep:') }}</p>
        <ul>
            <li>{{ __('Items with their stock and average cost') }}</li>
            <li>{{ __('Sale and purchase invoices, in any currency, paid now or on credit') }}</li>
            <li>{{ __('Returns, stock counts and a stock card for every item') }}</li>
            <li>{{ __('Reports of profit by item, stock on hand, and sales by customer') }}</li>
        </ul>
        <p class="mb-3">{{ __('Pro is :first for the first year and :renewal a year after. Your accounts and everything you have written stay as they are.', ['first' => '$'.($plans['pro']['first'] + 0), 'renewal' => '$'.($plans['pro']['renewal'] + 0)]) }}</p>
        <a class="btn btn-primary" href="{{ route('service.tickets') }}"><x-icon name="headset" class="me-1" /> {{ __('Ask for the Pro plan') }}</a>
        <a class="btn btn-outline-soft" href="{{ route('service.licence') }}">{{ __('Licence and Invoices') }}</a>
    </div>
</div>
@endsection
