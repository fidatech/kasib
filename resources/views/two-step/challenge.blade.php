@extends('layouts.auth', ['title' => __('Two-step sign-in')])

@section('content')
<h1 class="h4 mb-1">{{ __('Two-step sign-in') }}</h1>
<p class="text-muted">{{ __('Write the six-digit code of your authenticator app.') }}</p>
<form method="POST" action="{{ route('two-step.verify') }}">
    @csrf
    <div class="mb-3">
        <label class="form-label" for="code">{{ __('Code') }}</label>
        <input type="text" inputmode="numeric" dir="ltr" autocomplete="one-time-code" maxlength="7" class="form-control @error('code') is-invalid @enderror" id="code" name="code" required autofocus>
        @error('code')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
    </div>
    <button type="submit" class="btn btn-primary w-100">{{ __('Sign in') }}</button>
    <div class="text-center mt-3"><a href="{{ route('login') }}">{{ __('Back') }}</a></div>
</form>
@endsection
