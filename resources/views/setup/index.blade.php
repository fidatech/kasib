@extends('layouts.app', ['title' => __('First Setup')])

@section('content')
<x-page-header :title="__('First Setup')" icon="wand-magic-sparkles" :subtitle="__('Three choices and the system is ready. Everything here can be changed later.')" />

<form method="POST" action="{{ route('setup.store') }}" class="card">
    @csrf
    <div class="card-body">
        <h2 class="h5">{{ __('1. What kind of business is it?') }}</h2>
        <div class="row g-2 mb-4">
            @foreach ($kinds as $key => [$name, $about])
                <div class="col-12 col-md-6">
                    <label class="d-flex gap-3 align-items-start border rounded p-3 h-100" for="k-{{ $key }}">
                        <input class="form-check-input mt-1" type="radio" id="k-{{ $key }}" name="business_type" value="{{ $key }}" @checked(old('business_type', $chosen ?: 'exchange') === $key) required>
                        <span><span class="d-block fw-semibold">{{ __($name) }}</span><span class="d-block small text-muted">{{ __($about) }}</span></span>
                    </label>
                </div>
            @endforeach
        </div>
        @error('business_type')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror

        <h2 class="h5">{{ __('2. Which currencies does it work with?') }}</h2>
        <div class="row g-2 mb-4">
            @foreach ($currencies as $currency)
                <div class="col-6 col-md-3">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="c-{{ $currency->id }}" name="currencies[]" value="{{ $currency->id }}" @checked($currency->is_base || in_array($currency->id, (array) old('currencies', $currency->is_active ? [$currency->id] : []))) @disabled($currency->is_base)>
                        <label class="form-check-label" for="c-{{ $currency->id }}"><strong>{{ $currency->code }}</strong> <span class="text-muted small">{{ text_dir() === 'rtl' && $currency->name_local ? $currency->name_local : $currency->name }}@if ($currency->is_base) · {{ __('base currency') }}@endif</span></label>
                    </div>
                </div>
            @endforeach
        </div>

        <h2 class="h5">{{ __('3. How should it look?') }}</h2>
        <div class="row g-3">
            <div class="col-12 col-md-4">
                <label class="form-label" for="s-calendar">{{ __('Calendar') }}</label>
                <select class="form-select" id="s-calendar" name="calendar" data-plain>
                    <option value="shamsi" @selected(old('calendar', setting('calendar', 'shamsi')) === 'shamsi')>{{ __('Solar Hijri (Shamsi)') }}</option>
                    <option value="gregorian" @selected(old('calendar', setting('calendar', 'shamsi')) === 'gregorian')>{{ __('Gregorian') }}</option>
                </select>
            </div>
            <div class="col-12 col-md-8">
                <input type="hidden" name="simple_mode" value="0">
                <div class="form-check form-switch mt-md-4 pt-md-2">
                    <input class="form-check-input" type="checkbox" role="switch" id="s-simple" name="simple_mode" value="1" @checked((string) old('simple_mode', setting('simple_mode', '0')) === '1')>
                    <label class="form-check-label" for="s-simple">{{ __('Simple mode') }} <span class="d-block small text-muted">{{ __('For a small business: only accounts, money in and out, exchanges and the balances.') }}</span></label>
                </div>
            </div>
        </div>
    </div>
    <div class="card-footer d-flex gap-2 justify-content-end">
        <a class="btn btn-light-secondary" href="{{ route('dashboard') }}">{{ __('Later') }}</a>
        <button type="submit" class="btn btn-primary"><x-icon name="check" class="me-1" /> {{ $done ? __('Save') : __('Start working') }}</button>
    </div>
</form>
@endsection
