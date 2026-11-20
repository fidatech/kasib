@extends('layouts.app', ['title' => __('WhatsApp Messages')])

@section('content')
<x-page-header :title="__('WhatsApp Messages')" icon="comment-dots" :subtitle="__('A receipt to the customer after money in or money out, and a reminder before a payback date.')" />

@php $text = fn (string $key, ?string $default = null) => old($key, setting($key, $default)); @endphp
<form method="POST" action="{{ route('whatsapp.update') }}" class="card mb-3">
    @csrf
    @method('PUT')
    <div class="card-body">
        <input type="hidden" name="whatsapp_enabled" value="0">
        <div class="form-check form-switch mb-3">
            <input class="form-check-input" type="checkbox" role="switch" id="w-on" name="whatsapp_enabled" value="1" @checked((string) $text('whatsapp_enabled', '0') === '1') @disabled(! $canManage)>
            <label class="form-check-label fw-semibold" for="w-on">{{ __('Send WhatsApp messages') }}</label>
        </div>
        <div class="row g-3">
            <div class="col-12 col-md-8">
                <label class="form-label" for="w-url">{{ __('Address of the sender (API)') }}</label>
                <input type="url" dir="ltr" class="form-control @error('whatsapp_url') is-invalid @enderror" id="w-url" name="whatsapp_url" value="{{ $text('whatsapp_url') }}" placeholder="https://…/send">
                @error('whatsapp_url')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
            </div>
            <div class="col-12 col-md-4">
                <label class="form-label" for="w-token">{{ __('Key of the sender') }}</label>
                <input type="password" dir="ltr" class="form-control" id="w-token" name="whatsapp_token" value="" autocomplete="new-password" placeholder="{{ setting('whatsapp_token') ? __('Kept. Write only to change it.') : '' }}">
            </div>
            <div class="col-6 col-md-3">
                <label class="form-label" for="w-pf">{{ __('Name of the number field') }}</label>
                <input type="text" dir="ltr" class="form-control @error('whatsapp_phone_field') is-invalid @enderror" id="w-pf" name="whatsapp_phone_field" value="{{ $text('whatsapp_phone_field', 'number') }}" required>
            </div>
            <div class="col-6 col-md-3">
                <label class="form-label" for="w-mf">{{ __('Name of the message field') }}</label>
                <input type="text" dir="ltr" class="form-control @error('whatsapp_message_field') is-invalid @enderror" id="w-mf" name="whatsapp_message_field" value="{{ $text('whatsapp_message_field', 'message') }}" required>
            </div>
            <div class="col-6 col-md-3">
                <label class="form-label" for="w-cc">{{ __('Country code') }}</label>
                <input type="text" inputmode="numeric" dir="ltr" class="form-control @error('whatsapp_country') is-invalid @enderror" id="w-cc" name="whatsapp_country" value="{{ $text('whatsapp_country', '93') }}" required>
            </div>
            <div class="col-12"><div class="form-text">{{ __('The system sends a POST request with these two fields as JSON, and the key as a Bearer token. A number written as 0700123456 is sent as 93700123456.') }}</div></div>

            @foreach (['money_in' => 'Message after Money In', 'money_out' => 'Message after Money Out', 'paper' => 'Message after any other paper or invoice', 'due' => 'Reminder before the payback date'] as $kind => $label)
                <div class="col-12 col-md-6 col-xl-3">
                    <label class="form-label" for="w-t-{{ $kind }}">{{ __($label) }}</label>
                    <textarea class="form-control" id="w-t-{{ $kind }}" name="whatsapp_tpl_{{ $kind }}" rows="5" dir="auto" placeholder="{{ $templates[$kind] }}">{{ $text('whatsapp_tpl_'.$kind) }}</textarea>
                </div>
            @endforeach
            <div class="col-12"><div class="form-text" dir="ltr">{business} {name} {what} {amount} {currency} {number} {date} {due} {balance}</div><div class="form-text">{{ __('These words are replaced in a message. A message left empty uses the one shown in grey.') }}</div></div>
        </div>
    </div>
    @if ($canManage)
        <div class="card-footer d-flex gap-2 justify-content-end">
            <button type="submit" class="btn btn-primary"><x-icon name="check" class="me-1" /> {{ __('Save') }}</button>
        </div>
    @endif
</form>

@if ($canManage)
    <form method="POST" action="{{ route('whatsapp.test') }}" class="card">
        @csrf
        <div class="card-body">
            <div class="row g-2 align-items-end">
                <div class="col-12 col-md-5">
                    <label class="form-label" for="w-test">{{ __('Send a test message to') }}</label>
                    <input type="tel" dir="ltr" class="form-control @error('phone') is-invalid @enderror" id="w-test" name="phone" value="{{ old('phone') }}" placeholder="0700123456" required>
                </div>
                <div class="col-auto"><button type="submit" class="btn btn-outline-soft"><x-icon name="paper-plane" class="me-1" /> {{ __('Send') }}</button></div>
            </div>
        </div>
    </form>
@endif
@endsection
