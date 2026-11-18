@extends('layouts.app', ['title' => __('Alerts & Summary')])

@section('content')
<x-page-header :title="__('Alerts & Summary')" icon="bell" :subtitle="__('What the owner is told about by the system itself, and when.')" />

@php
    $text = fn (string $key, ?string $default = null) => old($key, setting($key, $default));
    $switch = fn (string $key, string $default = '0') => (string) $text($key, $default) === '1';
@endphp
<form method="POST" action="{{ route('owner.update') }}" class="card mb-3">
    @csrf
    @method('PUT')
    <div class="card-body">
        <div class="row g-3">
            <div class="col-12 col-md-4">
                <label class="form-label" for="o-phone">{{ __('WhatsApp number of the owner') }}</label>
                <input type="tel" dir="ltr" class="form-control @error('owner_phone') is-invalid @enderror" id="o-phone" name="owner_phone" value="{{ $text('owner_phone') }}" placeholder="0700123456" @disabled(! $canManage)>
            </div>
            <div class="col-12 col-md-4">
                <label class="form-label" for="o-locale">{{ __('Language of the messages to the owner') }}</label>
                <select class="form-select" id="o-locale" name="owner_locale" @disabled(! $canManage)>
                    @foreach (['en' => 'English', 'fa' => 'دری', 'ps' => 'پښتو'] as $code => $name)
                        <option value="{{ $code }}" @selected($text('owner_locale', app()->getLocale()) === $code)>{{ $name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="col-12"><hr class="my-1"><h2 class="h5 mb-0">{{ __('Unusual activity') }}</h2></div>
            <div class="col-12">
                <input type="hidden" name="alerts_enabled" value="0">
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" role="switch" id="o-alerts" name="alerts_enabled" value="1" @checked($switch('alerts_enabled', '1')) @disabled(! $canManage)>
                    <label class="form-check-label" for="o-alerts">{{ __('Tell the owner about unusual activity (under the bell)') }}</label>
                </div>
                <input type="hidden" name="alerts_whatsapp" value="0">
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" role="switch" id="o-alerts-wa" name="alerts_whatsapp" value="1" @checked($switch('alerts_whatsapp')) @disabled(! $canManage)>
                    <label class="form-check-label" for="o-alerts-wa">{{ __('Send each alert to the owner on WhatsApp too') }}</label>
                </div>
            </div>
            <div class="col-12 col-md-3">
                <label class="form-label" for="o-large">{{ __('A large amount is from') }}</label>
                <input type="text" inputmode="decimal" dir="ltr" class="form-control @error('alert_large') is-invalid @enderror" id="o-large" name="alert_large" value="{{ $text('alert_large') }}" @disabled(! $canManage)>
                <div class="form-text">{{ __('In the base currency. Empty: no alert for large amounts.') }}</div>
            </div>
            <div class="col-12 col-md-3">
                <label class="form-label" for="o-back">{{ __('Dated back more than (days)') }}</label>
                <input type="number" min="0" dir="ltr" class="form-control @error('alert_backdate') is-invalid @enderror" id="o-back" name="alert_backdate" value="{{ $text('alert_backdate', '2') }}" @disabled(! $canManage)>
                <div class="form-text">{{ __('0: no alert for dates.') }}</div>
            </div>
            <div class="col-6 col-md-3">
                <label class="form-label" for="o-from">{{ __('Working hours from') }}</label>
                <input type="number" min="0" max="23" dir="ltr" class="form-control @error('alert_from') is-invalid @enderror" id="o-from" name="alert_from" value="{{ $text('alert_from') }}" @disabled(! $canManage)>
            </div>
            <div class="col-6 col-md-3">
                <label class="form-label" for="o-to">{{ __('Working hours to') }}</label>
                <input type="number" min="1" max="24" dir="ltr" class="form-control @error('alert_to') is-invalid @enderror" id="o-to" name="alert_to" value="{{ $text('alert_to') }}" @disabled(! $canManage)>
                <div class="form-text">{{ __('Hours of the day, 0 to 24. Empty: no alert for the time.') }}</div>
            </div>
            @foreach (['alert_large', 'alert_backdate', 'alert_from', 'alert_to', 'summary_hour', 'confirm_day'] as $field)
                @error($field)<div class="col-12"><div class="invalid-feedback d-block">{{ $message }}</div></div>@enderror
            @endforeach

            <div class="col-12"><hr class="my-1"><h2 class="h5 mb-0">{{ __('Evening summary') }}</h2></div>
            <div class="col-12 col-md-6">
                <input type="hidden" name="summary_enabled" value="0">
                <div class="form-check form-switch mb-2">
                    <input class="form-check-input" type="checkbox" role="switch" id="o-sum" name="summary_enabled" value="1" @checked($switch('summary_enabled')) @disabled(! $canManage)>
                    <label class="form-check-label" for="o-sum">{{ __('Send the owner a summary of the day every evening') }}</label>
                </div>
                <label class="form-label" for="o-hour">{{ __('At the hour') }}</label>
                <input type="number" min="0" max="23" dir="ltr" class="form-control" id="o-hour" name="summary_hour" value="{{ $text('summary_hour', '20') }}" @disabled(! $canManage)>
            </div>
            <div class="col-12 col-md-6">
                <label class="form-label">{{ __('The summary of now') }}</label>
                <pre class="border rounded p-2 small mb-0" dir="auto" style="white-space: pre-wrap">{{ $summary }}</pre>
            </div>

            <div class="col-12"><hr class="my-1"><h2 class="h5 mb-0">{{ __('Balance confirmation') }}</h2></div>
            <div class="col-12 col-md-4">
                <label class="form-label" for="o-day">{{ __('Day of the month') }}</label>
                <input type="number" min="0" max="28" dir="ltr" class="form-control" id="o-day" name="confirm_day" value="{{ $text('confirm_day', '0') }}" @disabled(! $canManage)>
                <div class="form-text">{{ __('On this day every customer and supplier with a phone number is sent his balance. 0: never by itself.') }}</div>
            </div>
            <div class="col-12 col-md-8">
                <label class="form-label" for="o-tpl">{{ __('The message') }}</label>
                <textarea class="form-control" id="o-tpl" name="whatsapp_tpl_confirm" rows="5" dir="auto" placeholder="{{ $confirm }}" @disabled(! $canManage)>{{ $text('whatsapp_tpl_confirm') }}</textarea>
                <div class="form-text" dir="ltr">{business} {name} {date} {balance}</div>
            </div>
        </div>
    </div>
    @if ($canManage)
        <div class="card-footer d-flex gap-2 justify-content-end">
            <button type="submit" class="btn btn-primary"><x-icon name="check" class="me-1" /> {{ __('Save') }}</button>
        </div>
    @endif
</form>

@if ($canManage)
    <form method="POST" action="{{ route('owner.summary') }}" class="d-inline">
        @csrf
        <button type="submit" class="btn btn-outline-soft"><x-icon name="paper-plane" class="me-1" /> {{ __('Send the summary now') }}</button>
    </form>
@endif
@endsection
