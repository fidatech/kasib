@extends('layouts.site', ['title' => __('The business system to the rules of Da Afghanistan Bank')])

@php
    $features = [
        ['money-bill-transfer', 'Exchange and remittance', 'Buying and selling of currencies, outgoing and incoming remittances, with receipts ready to print.'],
        ['users', 'Customers to the rules of the bank', 'Identification of customers, risk levels and the documents asked for from each amount.'],
        ['code-branch', 'Every branch in one place', 'The main branch and the others work in one system, each with its own treasury and its own day.'],
        ['file-excel', 'Reports of Da Afghanistan Bank', 'The balance sheet, profit and loss and indicators in the workbook of the bank, quarter by quarter.'],
        ['book', 'Real accounting', 'Journal, ledger, trial balance, expenses, assets and depreciation, without a second program.'],
        ['language', 'Dari, Pashto and English', 'Three languages, Solar Hijri and Gregorian dates, on the computer and on the phone.'],
    ];
@endphp

@section('content')
<section class="site-hero">
    <div class="site-wrap">
        <div>
            <span class="site-kicker"><x-icon name="landmark" /> {{ __('app.auth.hero_kicker') }}</span>
            <h1>{{ __('app.auth.hero_title') }}</h1>
            <p>{{ __('app.auth.hero_text') }}</p>
            <div class="site-actions">
                <a class="btn btn-light btn-lg" href="{{ route('site.register') }}">{{ __('Register your business') }} <x-icon name="arrow-right" class="ms-1" /></a>
                <a class="btn btn-outline-light btn-lg" href="#signin">{{ __('Sign in') }}</a>
            </div>
        </div>
        <ul class="site-points">
            <li><x-icon name="circle-check" /> {{ __('Your own address: name.:domain', ['domain' => config('tenancy.base_domain')]) }}</li>
            <li><x-icon name="circle-check" /> {{ __('Your own database, kept apart from every other business') }}</li>
            <li><x-icon name="circle-check" /> {{ __('A backup every day') }}</li>
            <li><x-icon name="circle-check" /> {{ __('Support from Fida Technologies in Kabul') }}</li>
        </ul>
    </div>
</section>

<section class="site-section" id="features">
    <div class="site-wrap">
        <h2>{{ __('What the system does') }}</h2>
        <div class="site-grid">
            @foreach ($features as [$icon, $name, $text])
                <article class="site-card"><span class="site-icon"><x-icon :name="$icon" /></span><h3>{{ __($name) }}</h3><p>{{ __($text) }}</p></article>
            @endforeach
        </div>
    </div>
</section>

<section class="site-section site-tint" id="pricing">
    <div class="site-wrap">
        <h2>{{ __('Prices') }}</h2>
        <p class="site-lead">{{ __('In US dollars, for a year. The first year covers the setup; the years after are the hosting.') }}</p>
        @if ($trial ?? null)<p class="site-lead"><strong>{{ __('Free for :days days. Your system opens at once; nothing is paid now.', ['days' => $trial]) }}</strong> <a href="{{ url('/register') }}">{{ __('Register your business') }}</a></p>@endif
        <div class="site-prices">
            <article class="site-price">
                <h3>{{ __('Subscription') }}</h3>
                <p class="site-amount"><span dir="ltr">${{ $prices['main_first'] + 0 }}</span> <small>{{ __('first year') }}</small></p>
                <p>{{ __('then :amount a year for hosting', ['amount' => '$'.($prices['main_renewal'] + 0)]) }}</p>
            </article>
        </div>
    </div>
</section>

<section class="site-section">
    <div class="site-wrap">
        <h2>{{ __('How to start') }}</h2>
        <ol class="site-steps">
            <li><strong>{{ __('Register') }}</strong><span>{{ __('Fill in the form with the name of your business and the address you want.') }}</span></li>
            <li><strong>{{ __('We check and approve') }}</strong><span>{{ __('Our team looks at the request and contacts you.') }}</span></li>
            <li><strong>{{ __('Sign in and work') }}</strong><span>{{ __('Your system is opened at your own address, with the password you chose.') }}</span></li>
        </ol>
    </div>
</section>

<section class="site-section site-tint" id="signin">
    <div class="site-wrap site-signin">
        <div>
            <h2>{{ __('Already a client?') }}</h2>
            <p class="site-lead">{{ __('Every business signs in at its own address. Type yours and we take you there.') }}</p>
        </div>
        <form method="GET" action="{{ route('site.open') }}" class="site-open">
            <label class="form-label" for="address">{{ __('Address of your business') }}</label>
            <div class="input-group" dir="ltr">
                <input type="text" class="form-control" id="address" name="address" required autocomplete="off" autocapitalize="none" spellcheck="false" placeholder="kabul">
                <span class="input-group-text">.{{ config('tenancy.base_domain') }}</span>
                <button class="btn btn-primary" type="submit">{{ __('Sign in') }}</button>
            </div>
        </form>
    </div>
</section>
@endsection
