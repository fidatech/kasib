@extends('layouts.app', ['title' => __('Two-step sign-in')])

@section('content')
<x-page-header :title="__('Two-step sign-in')" icon="shield-halved" :subtitle="__('After your password, the system asks for a code from an authenticator app on your phone.')" />

<div class="card">
    <div class="card-body">
        @if ($on)
            <div class="alert alert-success">{{ __('Two-step sign-in is on for your user.') }}</div>
            <form method="POST" action="{{ route('two-step.disable') }}" class="row g-2 align-items-end">
                @csrf
                @method('DELETE')
                <div class="col-12 col-md-4">
                    <label class="form-label" for="ts-password">{{ __('Your password') }}</label>
                    <input type="password" class="form-control @error('password') is-invalid @enderror" id="ts-password" name="password" autocomplete="current-password" required>
                    @error('password')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                </div>
                <div class="col-auto"><button type="submit" class="btn btn-outline-soft">{{ __('Switch it off') }}</button></div>
            </form>
        @elseif ($secret)
            <div class="row g-4 align-items-center">
                <div class="col-auto">{!! $qr !!}</div>
                <div class="col">
                    <ol class="mb-3">
                        <li>{{ __('Open an authenticator app on your phone (Google Authenticator, Microsoft Authenticator or another).') }}</li>
                        <li>{{ __('Scan this picture with it, or type this key:') }} <code dir="ltr">{{ trim(chunk_split($secret, 4, ' ')) }}</code></li>
                        <li>{{ __('Write the six-digit code the app shows.') }}</li>
                    </ol>
                    <form method="POST" action="{{ route('two-step.confirm') }}" class="row g-2 align-items-end">
                        @csrf
                        <div class="col-8 col-md-4">
                            <label class="form-label" for="ts-code">{{ __('Code') }}</label>
                            <input type="text" inputmode="numeric" dir="ltr" autocomplete="one-time-code" maxlength="7" class="form-control @error('code') is-invalid @enderror" id="ts-code" name="code" required autofocus>
                            @error('code')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-auto"><button type="submit" class="btn btn-primary"><x-icon name="check" class="me-1" /> {{ __('Switch it on') }}</button></div>
                    </form>
                </div>
            </div>
        @else
            <p>{{ __('With two-step sign-in, somebody who learns your password still cannot enter without your phone.') }}</p>
            <form method="POST" action="{{ route('two-step.start') }}">
                @csrf
                <button type="submit" class="btn btn-primary"><x-icon name="shield-halved" class="me-1" /> {{ __('Set it up') }}</button>
            </form>
        @endif
    </div>
</div>
@endsection
