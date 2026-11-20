@extends('layouts.auth', ['title' => __('app.auth.title')])

@section('content')
<div class="auth-card">
    <div class="ac-icon"><x-icon name="right-to-bracket" /></div>
    <h2>{{ __('app.auth.welcome') }}</h2>
    <p class="ac-sub">{{ __('app.auth.subtitle') }}</p>
    @php($demo = \App\Services\Demo::is(tenant()) ? config('tenancy.demo') : null)
    @if ($demo)
        <div class="alert alert-info text-start" role="note">
            <strong>{{ \App\Services\Demo::planOf(tenant()) === 'starter' ? __('This is the Starter demo.') : __('This is the Pro demo.') }}</strong> {{ __('Sign in with:') }}
            <div dir="ltr" class="mt-1"><span class="user-select-all">{{ $demo['email'] }}</span> · <span class="user-select-all">{{ $demo['password'] }}</span></div>
        </div>
    @endif

    <form method="POST" action="{{ route('login.store') }}">
        @csrf
        <div class="mb-3">
            <label class="form-label" for="email">{{ __('app.auth.email') }}</label>
            <div class="auth-field"><x-icon name="envelope" set="regular" /><input type="email" id="email" name="email" value="{{ old('email', $demo['email'] ?? '') }}" class="form-control @error('email') is-invalid @enderror" autocomplete="username" required autofocus></div>
            @error('email')
                <div class="invalid-feedback d-block">{{ $message }}</div>
            @enderror
        </div>
        <div class="mb-3">
            <label class="form-label" for="password">{{ __('app.auth.password') }}</label>
            <div class="auth-field"><x-icon name="lock" /><input type="password" id="password" name="password" value="{{ $demo['password'] ?? '' }}" class="form-control @error('password') is-invalid @enderror" autocomplete="current-password" required><button type="button" class="field-eye" data-toggle-password aria-label="{{ __('app.auth.show_password') }}"><x-icon name="eye" set="regular" /></button></div>
            @error('password')
                <div class="invalid-feedback d-block">{{ $message }}</div>
            @enderror
        </div>
        <div class="form-check mb-4">
            <input class="form-check-input" type="checkbox" id="remember" name="remember" value="1">
            <label class="form-check-label small" for="remember">{{ __('app.auth.remember') }}</label>
        </div>
        <button type="submit" class="btn btn-gradient btn-lg w-100">{{ __('app.auth.submit') }} <x-icon name="arrow-right" class="ms-1" /></button>
    </form>
    <form method="POST" action="{{ route('preferences.locale') }}" class="auth-languages">
        @csrf
        @foreach (config('app.locales') as $code => $language)
            <button type="submit" name="locale" value="{{ $code }}" lang="{{ $code }}" class="{{ app()->getLocale() === $code ? 'active' : '' }}">{{ $language }}</button>
        @endforeach
    </form>
</div>
@endsection
