@extends('layouts.app', ['title' => __('My Businesses')])

@section('content')
<x-page-header :title="__('My Businesses')" icon="building" :subtitle="__('If you have a user in another business, link it once and open it from here without signing in again.')" />

<div class="card mb-3">
    <div class="card-body">
        @if ($links->isEmpty())
            <div class="empty-state"><div class="es-icon"><x-icon name="building" /></div><h2 class="h3">{{ __('No other business is linked yet') }}</h2></div>
        @else
            <table class="table table-hover w-100 keep-table">
                <thead><tr><th>{{ __('Business') }}</th><th>{{ __('Address') }}</th><th>{{ __('Your user there') }}</th><th class="text-end">{{ __('Actions') }}</th></tr></thead>
                <tbody>
                    @foreach ($links as $link)
                        <tr>
                            <td>{{ $link->tenant?->brand_name ?: $link->tenant?->name }}</td>
                            <td dir="ltr">{{ $link->tenant?->domain }}</td>
                            <td dir="ltr">{{ $link->email }}</td>
                            <td class="text-end text-nowrap">
                                <form method="POST" action="{{ route('businesses.open', ['id' => $link->id]) }}" class="d-inline">@csrf<button type="submit" class="btn btn-sm btn-primary">{{ __('Open') }}</button></form>
                                <form method="POST" action="{{ route('businesses.destroy', ['id' => $link->id]) }}" class="d-inline" data-confirm="{{ __('Remove this link?') }}" data-confirm-ok="{{ __('Remove') }}">@csrf @method('DELETE')<button type="submit" class="btn btn-sm btn-light-secondary">{{ __('Remove') }}</button></form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
</div>

<form method="POST" action="{{ route('businesses.store') }}" class="card">
    @csrf
    <div class="card-header"><h2 class="h5 mb-0">{{ __('Link another business') }}</h2></div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-12 col-md-4">
                <label class="form-label" for="b-address">{{ __('Its address') }} <span class="req">*</span></label>
                <div class="input-group" dir="ltr"><input type="text" class="form-control @error('address') is-invalid @enderror" id="b-address" name="address" value="{{ old('address') }}" required autocapitalize="none" spellcheck="false"><span class="input-group-text">.{{ config('tenancy.base_domain') }}</span></div>
                @error('address')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
            </div>
            <div class="col-12 col-md-3">
                <label class="form-label" for="b-email">{{ __('Your email there') }} <span class="req">*</span></label>
                <input type="email" dir="ltr" class="form-control" id="b-email" name="email" value="{{ old('email', auth()->user()->email) }}" required>
            </div>
            <div class="col-12 col-md-3">
                <label class="form-label" for="b-password">{{ __('Your password there') }} <span class="req">*</span></label>
                <input type="password" dir="ltr" class="form-control" id="b-password" name="password" required autocomplete="off">
            </div>
            <div class="col-12 col-md-2">
                <label class="form-label" for="b-code">{{ __('Two-step code') }}</label>
                <input type="text" inputmode="numeric" dir="ltr" class="form-control @error('code') is-invalid @enderror" id="b-code" name="code" autocomplete="off">
                @error('code')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
            </div>
            <div class="col-12"><div class="form-text">{{ __('The password is checked once, to prove the user is yours; it is not kept. The other business shows this one on its own page from then on.') }}</div></div>
        </div>
    </div>
    <div class="card-footer d-flex justify-content-end"><button type="submit" class="btn btn-primary"><x-icon name="link" class="me-1" /> {{ __('Link it') }}</button></div>
</form>
@endsection
