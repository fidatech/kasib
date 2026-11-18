@extends('layouts.site', ['title' => __('Register your business')])

@section('content')
@php
    $chosen = old('plan', request('plan', 'starter'));
    $chosen = isset($plans[$chosen]) ? $chosen : 'starter';
    $places = $provinces->mapWithKeys(fn ($p) => [$p->name => $p->cities->map(fn ($c) => ['id' => $c->name, 'text' => $c->label(), 'alt' => $c->name.' '.$c->name_local])->all()]);
@endphp
<link rel="stylesheet" href="{{ asset_v('vendor/select2/select2.min.css') }}">
<link rel="stylesheet" href="{{ asset_v('css/register.css') }}">

<section class="site-section reg">
    <div class="site-wrap">
        <div class="reg-head">
            <h1 class="site-title">{{ __('Register your business') }}</h1>
            <p class="site-lead">{{ ($trial ?? null) ? __('Free for :days days. Your system opens at once; nothing is paid now.', ['days' => $trial]) : __('Fill in the form. Our team checks the request; once it is approved, your system is opened at your own address.') }}</p>
        </div>

        <div class="reg-grid">
            <form method="POST" action="{{ route('site.register.store') }}" class="reg-form" id="reg-form" novalidate
                data-check="{{ route('site.register.check') }}" data-domain="{{ config('tenancy.base_domain') }}" data-dir="{{ text_dir() }}" data-lang="{{ app()->getLocale() }}"
                data-t-checking="{{ __('Checking…') }}" data-t-province="{{ __('Choose a province') }}" data-t-city="{{ __('Choose or write a city') }}" data-t-city-first="{{ __('Choose the province first') }}"
                data-t-weak="{{ __('Too short') }}" data-t-fair="{{ __('Good') }}" data-t-strong="{{ __('Strong') }}" data-t-match="{{ __('The two passwords are the same.') }}" data-t-nomatch="{{ __('The two passwords are not the same.') }}"
                data-t-required="{{ __('This box is needed.') }}" data-t-email="{{ __('Write a real email address.') }}" data-t-wait="{{ __('One moment…') }}" data-t-show="{{ __('Show') }}" data-t-hide="{{ __('Hide') }}">
                @csrf
                @if ($errors->any())
                    <div class="alert alert-danger" role="alert">{{ __('Please correct the boxes marked below.') }}</div>
                @endif
                <input type="hidden" name="ref" value="{{ old('ref', request('ref')) }}">
                <div class="site-trap" aria-hidden="true"><label for="website">Website</label><input type="text" id="website" name="website" tabindex="-1" autocomplete="off"></div>

                <fieldset class="reg-step">
                    <legend><span class="reg-num">1</span>{{ __('The plan') }}</legend>
                    <div class="reg-plans" role="radiogroup" aria-label="{{ __('The plan') }}">
                        @foreach ($plans as $key => $plan)
                            <label class="reg-plan">
                                <input type="radio" name="plan" value="{{ $key }}" @checked($chosen === $key) data-name="{{ __($plan['name']) }}" data-first="${{ $plan['first'] + 0 }}" data-renewal="${{ $plan['renewal'] + 0 }}">
                                <span class="reg-plan-body">
                                    <span class="reg-plan-name">{{ __($plan['name']) }} @if ($key === 'pro')<span class="reg-tag">{{ __('Everything') }}</span>@endif</span>
                                    <span class="reg-plan-price"><span dir="ltr">${{ $plan['first'] + 0 }}</span> <small>{{ __('first year') }}</small></span>
                                    <span class="reg-plan-then">{{ __('then :amount a year', ['amount' => '$'.($plan['renewal'] + 0)]) }}</span>
                                    @foreach (array_slice($plan['notes'], 0, 4) as $note)<span class="reg-plan-note"><x-icon name="check" /> {{ $note }}</span>@endforeach
                                </span>
                            </label>
                        @endforeach
                    </div>
                </fieldset>

                <fieldset class="reg-step">
                    <legend><span class="reg-num">2</span>{{ __('The business') }}</legend>
                    <div class="row g-3">
                        <div class="col-12 col-md-6">
                            <label class="form-label" for="name">{{ __('Name of the business') }} <span class="req">*</span></label>
                            <input type="text" class="form-control @error('name') is-invalid @enderror" id="name" name="name" value="{{ old('name') }}" maxlength="150" required autocomplete="organization">
                            <div class="invalid-feedback">@error('name'){{ $message }}@enderror</div>
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label" for="name_local">{{ __('Name in Dari or Pashto') }}</label>
                            <input type="text" class="form-control" id="name_local" name="name_local" value="{{ old('name_local') }}" maxlength="150" dir="rtl">
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="slug">{{ __('Address you want') }} <span class="req">*</span></label>
                            <div class="input-group" dir="ltr">
                                <input type="text" class="form-control @error('slug') is-invalid @enderror" id="slug" name="slug" value="{{ old('slug') }}" maxlength="40" required autocomplete="off" autocapitalize="none" spellcheck="false" placeholder="kabul" aria-describedby="slugHelp slugState" pattern="[a-z][a-z0-9-]{2,39}">
                                <span class="input-group-text">.{{ config('tenancy.base_domain') }}</span>
                            </div>
                            <div class="reg-state @error('slug') is-bad @enderror" id="slugState" aria-live="polite">@error('slug'){{ $message }}@enderror</div>
                            <div class="form-text" id="slugHelp">{{ __('Small English letters, digits and dashes. Your staff open the system at this address.') }}</div>
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label" for="province">{{ __('Province') }}</label>
                            <select class="form-select @error('province') is-invalid @enderror" id="province" name="province" data-placeholder="{{ __('Choose a province') }}">
                                <option value=""></option>
                                @foreach ($provinces as $province)
                                    <option value="{{ $province->name }}" data-alt="{{ $province->name }} {{ $province->name_fa }} {{ $province->name_ps }}" @selected(old('province') === $province->name)>{{ $province->label() }}</option>
                                @endforeach
                            </select>
                            <div class="invalid-feedback">@error('province'){{ $message }}@enderror</div>
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label" for="city">{{ __('City') }}</label>
                            <select class="form-select" id="city" name="city" data-placeholder="{{ __('Choose or write a city') }}" data-old="{{ old('city') }}">
                                <option value=""></option>
                                @foreach ($provinces as $province)
                                    <optgroup label="{{ $province->label() }}" data-province="{{ $province->name }}">
                                        @foreach ($province->cities as $city)
                                            <option value="{{ $city->name }}" @selected(old('city') === $city->name && old('province') === $province->name)>{{ $city->label() }}</option>
                                        @endforeach
                                    </optgroup>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </fieldset>

                <fieldset class="reg-step">
                    <legend><span class="reg-num">3</span>{{ __('The owner') }}</legend>
                    <div class="row g-3">
                        <div class="col-12 col-md-6">
                            <label class="form-label" for="owner_name">{{ __('Full name') }} <span class="req">*</span></label>
                            <input type="text" class="form-control @error('owner_name') is-invalid @enderror" id="owner_name" name="owner_name" value="{{ old('owner_name') }}" maxlength="150" required autocomplete="name">
                            <div class="invalid-feedback">@error('owner_name'){{ $message }}@enderror</div>
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label" for="owner_phone">{{ __('Phone') }} <span class="req">*</span></label>
                            <input type="tel" class="form-control @error('owner_phone') is-invalid @enderror" id="owner_phone" name="owner_phone" value="{{ old('owner_phone') }}" minlength="7" maxlength="40" required dir="ltr" autocomplete="tel" inputmode="tel" placeholder="07XX XXX XXX">
                            <div class="invalid-feedback">@error('owner_phone'){{ $message }}@enderror</div>
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="owner_email">{{ __('Email') }} <span class="req">*</span></label>
                            <input type="email" class="form-control @error('owner_email') is-invalid @enderror" id="owner_email" name="owner_email" value="{{ old('owner_email') }}" maxlength="190" required dir="ltr" autocomplete="email" aria-describedby="emailHelp" placeholder="you@example.com">
                            <div class="invalid-feedback">@error('owner_email'){{ $message }}@enderror</div>
                            <div class="form-text" id="emailHelp">{{ __('You sign in with this email and the password below.') }}</div>
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label" for="password">{{ __('Password') }} <span class="req">*</span></label>
                            <div class="input-group">
                                <input type="password" class="form-control @error('password') is-invalid @enderror" id="password" name="password" minlength="8" required autocomplete="new-password" dir="ltr" aria-describedby="passState">
                                <button type="button" class="btn btn-outline-soft reg-eye" data-eye="password">{{ __('Show') }}</button>
                            </div>
                            <div class="reg-meter" aria-hidden="true"><span id="passBar"></span></div>
                            <div class="reg-state @error('password') is-bad @enderror" id="passState" aria-live="polite">@error('password'){{ $message }}@else{{ __('At least 8 letters or digits.') }}@enderror</div>
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label" for="password_confirmation">{{ __('Password again') }} <span class="req">*</span></label>
                            <div class="input-group">
                                <input type="password" class="form-control" id="password_confirmation" name="password_confirmation" minlength="8" required autocomplete="new-password" dir="ltr" aria-describedby="matchState">
                                <button type="button" class="btn btn-outline-soft reg-eye" data-eye="password_confirmation">{{ __('Show') }}</button>
                            </div>
                            <div class="reg-state" id="matchState" aria-live="polite"></div>
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="message">{{ __('Anything we should know') }}</label>
                            <textarea class="form-control" id="message" name="message" rows="2" maxlength="1000">{{ old('message') }}</textarea>
                        </div>
                    </div>
                </fieldset>

                <div class="reg-submit">
                    <button type="submit" class="btn btn-primary btn-lg" id="reg-go">{{ ($trial ?? null) ? __('Open my system') : __('Send the request') }}</button>
                    <span class="small text-muted">{{ ($trial ?? null) ? __('No card and no payment is asked for now.') : __('Our team checks the request and contacts you.') }}</span>
                </div>
            </form>

            <aside class="reg-side" aria-label="{{ __('Your choice') }}">
                <div class="reg-card">
                    <div class="reg-side-label">{{ __('Your choice') }}</div>
                    <div class="reg-side-plan" id="sidePlan">{{ __($plans[$chosen]['name']) }}</div>
                    <div class="reg-side-price"><span dir="ltr" id="sideFirst">${{ $plans[$chosen]['first'] + 0 }}</span> <small>{{ __('first year') }}</small></div>
                    <div class="reg-side-then">{{ __('Every year after') }}: <span dir="ltr" id="sideRenewal">${{ $plans[$chosen]['renewal'] + 0 }}</span></div>
                    @if ($trial ?? null)<div class="reg-side-trial"><x-icon name="gift" /> {{ __(':days days free, then you decide.', ['days' => $trial]) }}</div>@endif
                    <div class="reg-side-address"><span>{{ __('Your address') }}</span><strong dir="ltr" id="sideAddress">—</strong></div>
                </div>
                <ol class="reg-next">
                    <li><strong>{{ __('Fill in the form') }}</strong><span>{{ __('Two minutes. Only the boxes with a star are needed.') }}</span></li>
                    <li><strong>{{ ($trial ?? null) ? __('Your system opens') : __('We check the request') }}</strong><span>{{ ($trial ?? null) ? __('At your own address, with your email and password.') : __('You are told when your system is open.') }}</span></li>
                    <li><strong>{{ __('Start recording') }}</strong><span>{{ __('Accounts, money in and out, exchange and reports, in Dari, Pashto or English.') }}</span></li>
                </ol>
                @if ($company['company_phone'])
                    <p class="reg-help">{{ __('Need help? Call us:') }} <a dir="ltr" href="tel:{{ preg_replace('/[^0-9+]/', '', $company['company_phone']) }}">{{ $company['company_phone'] }}</a></p>
                @endif
            </aside>
        </div>
    </div>
</section>

<script id="reg-places" type="application/json">@json($places)</script>
<script src="{{ asset_v('vendor/jquery/jquery.min.js') }}" defer></script>
<script src="{{ asset_v('vendor/select2/select2.min.js') }}" defer></script>
@if (in_array(app()->getLocale(), ['fa', 'ps'], true))<script src="{{ asset_v('vendor/select2/i18n/'.app()->getLocale().'.js') }}" defer></script>@endif
<script src="{{ asset_v('js/register.js') }}" defer></script>
@endsection
