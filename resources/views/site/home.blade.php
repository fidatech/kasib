@extends('layouts.site', ['title' => __('Simple accounting for every business')])

@php
    $features = [
        ['wallet', 'A balance for every currency', 'Afghani, dollar, or any currency you add: each account keeps them apart, so you always see what you hold and what you owe.'],
        ['receipt', 'Money in and money out', 'Write a receipt in seconds. Print it in three languages or send it to the customer on WhatsApp with his new balance.'],
        ['right-left', 'Exchange profit, counted for you', 'Record an exchange as it happened. The gain or loss against your rate is worked out by the system.'],
        ['link', 'Statements customers can open', 'Send a private link and the customer reads his own balance and latest lines, without calling you.'],
        ['chart-column', 'Reports and Excel', 'Profit and loss, balance sheet, cash forecast and more. Every page downloads as Excel or PDF.'],
        ['database', 'Your own database, saved every night', 'Each business has a separate database. A backup is made every night and you can download one any time.'],
    ];
    $steps = ($trial ?? null)
        ? [['Register and choose your address', 'Your system opens at once at yourname.:domain.'], ['Add your accounts', 'Type them in, or bring them from Excel with their opening balances.'], ['Start writing', 'Money in, money out, exchange, expense. The books stay balanced behind the forms.']]
        : [['Register', 'Fill in the form with the name of your business and the address you want.'], ['We check and approve', 'Our team looks at the request and contacts you.'], ['Sign in and work', 'Your system is opened at your own address, with the password you chose.']];
    $position = [['Cash box', 'AFN', '120,000.00'], ['Cash box', 'USD', '3,450.00'], ['Bank', 'AFN', '86,500.00'], ['Saraf', 'USD', '1,200.00']];
@endphp

@section('content')
<section class="site-hero">
    <div class="site-wrap">
        <div class="site-hero-text">
            <span class="site-kicker">{{ __('Simple accounting for every business') }}</span>
            <h1>{{ __('Know what you hold, what you owe, and what you earned.') }}</h1>
            <p>{{ __(':name keeps the accounts of your business in plain forms: money in, money out, exchange. No debit and credit to learn. English, Dari and Pashto, with Shamsi dates.', ['name' => config('app.name')]) }}</p>
            <div class="site-actions">
                <a class="btn btn-primary btn-lg" href="{{ route('site.register') }}">{{ __('Register your business') }}</a>
                <a class="btn btn-outline-soft btn-lg" href="#features">{{ __('See what it does') }}</a>
            </div>
            @if ($trial ?? null)<p class="site-note">{{ __('Free for :days days. Your system opens at once; nothing is paid now.', ['days' => $trial]) }}</p>@endif
        </div>
        <div class="site-sample" aria-label="{{ __('Example') }}">
            <div class="site-sample-head"><strong>{{ __('Currency Position') }}</strong><span>{{ __('Example') }}</span></div>
            @foreach ($position as [$account, $currency, $amount])
                <div class="site-sample-row"><span>{{ __($account) }}</span><span class="cur">{{ $currency }}</span><span class="num" dir="ltr">{{ $amount }}</span></div>
            @endforeach
            <div class="site-sample-total"><span>{{ __('Exchange profit this month') }}</span><span class="num" dir="ltr">+ 18,240.00 AFN</span></div>
        </div>
    </div>
</section>

<section class="site-section site-tint" id="features">
    <div class="site-wrap">
        <h2>{{ __('Everything a business that handles money needs, and nothing it does not.') }}</h2>
        <div class="site-grid">
            @foreach ($features as [$icon, $name, $text])
                <article class="site-card"><span class="site-icon"><x-icon :name="$icon" /></span><h3>{{ __($name) }}</h3><p>{{ __($text) }}</p></article>
            @endforeach
        </div>
    </div>
</section>

<section class="site-section" id="how">
    <div class="site-wrap">
        <h2>{{ __('Working the same day') }}</h2>
        <ol class="site-steps">
            @foreach ($steps as $n => [$name, $text])
                <li><em>{{ __('Step :n', ['n' => $n + 1]) }}</em><strong>{{ __($name) }}</strong><span>{{ __($text, ['domain' => config('tenancy.base_domain')]) }}</span></li>
            @endforeach
        </ol>
    </div>
</section>

<section class="site-section" id="pricing">
    <div class="site-wrap">
        <div class="site-price-band">
            <div class="site-price-intro">
                <h2>{{ __('Two plans. One clear price each.') }}</h2>
                <p>{{ __('In US dollars. The first year includes the setup; after that you pay by the year.') }}</p>
                @if ($trial ?? null)<p><strong>{{ __('Free for :days days. Your system opens at once; nothing is paid now.', ['days' => $trial]) }}</strong></p>@endif
            </div>
            <div class="site-prices">
                @foreach ($plans as $key => $plan)
                    <article class="site-price">
                        <h3>{{ __($plan['name']) }}</h3>
                        <p class="site-amount"><span dir="ltr">${{ $plan['first'] + 0 }}</span> <small>{{ __('first year') }}</small></p>
                        <p>{{ __('then :amount a year', ['amount' => '$'.($plan['renewal'] + 0)]) }}</p>
                        @if ($plan['notes'])
                            <ul>@foreach ($plan['notes'] as $note)<li>{{ $note }}</li>@endforeach</ul>
                        @endif
                        <a class="btn btn-primary w-100" href="{{ route('site.register', ['plan' => $key]) }}">{{ __('Choose :plan', ['plan' => __($plan['name'])]) }}</a>
                    </article>
                @endforeach
            </div>
        </div>
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
