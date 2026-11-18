@extends('layouts.app', ['title' => __('Connections')])

@section('content')
<x-page-header :title="__('Connections')" icon="plug" :subtitle="__('Let SarafBook or another system read your accounts and record money in and money out, with a key.')" />

@if (session('new_key'))
    <div class="alert alert-warning">
        <div class="fw-semibold mb-1">{{ __('Your new key') }}</div>
        <code dir="ltr" class="user-select-all d-block text-break">{{ session('new_key') }}</code>
    </div>
@endif

<div class="card mb-3">
    <div class="card-body">
        @if ($keys->isEmpty())
            <div class="empty-state"><div class="es-icon"><x-icon name="key" /></div><h2 class="h3">{{ __('No key was made yet') }}</h2></div>
        @else
            <table class="table table-hover w-100 keep-table">
                <thead><tr><th>{{ __('Name') }}</th><th>{{ __('Key') }}</th><th>{{ __('Branch') }}</th><th>{{ __('May') }}</th><th>{{ __('Last used') }}</th><th class="text-end">{{ __('Actions') }}</th></tr></thead>
                <tbody>
                    @foreach ($keys as $key)
                        <tr @class(['text-muted' => $key->revoked_at])>
                            <td>{{ $key->name }}<div class="small text-muted">{{ $key->user?->name }}</div></td>
                            <td dir="ltr"><code>{{ $key->prefix }}…</code></td>
                            <td>{{ $key->branch?->name }}</td>
                            <td>{{ $key->can_write ? __('Read and record') : __('Read only') }}</td>
                            <td>{{ $key->last_used_at ? $key->last_used_at->diffForHumans() : __('Never') }}</td>
                            <td class="text-end text-nowrap">
                                @if ($key->revoked_at)
                                    <span class="badge bg-secondary">{{ __('Switched off') }}</span>
                                @else
                                    <form method="POST" action="{{ route('connections.destroy', ['id' => $key->id]) }}" class="d-inline" data-confirm="{{ __('Switch this key off? The system that uses it stops working at once.') }}" data-confirm-ok="{{ __('Switch off') }}">@csrf @method('DELETE')<button type="submit" class="btn btn-sm btn-outline-danger">{{ __('Switch off') }}</button></form>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
</div>

<form method="POST" action="{{ route('connections.store') }}" class="card mb-3">
    @csrf
    <div class="card-header"><h2 class="h5 mb-0">{{ __('Make a key') }}</h2></div>
    <div class="card-body">
        <div class="row g-3 align-items-end">
            <div class="col-12 col-md-4">
                <label class="form-label" for="k-name">{{ __('What it is for') }} <span class="req">*</span></label>
                <input type="text" class="form-control @error('name') is-invalid @enderror" id="k-name" name="name" value="{{ old('name', 'SarafBook') }}" required maxlength="100">
                @error('name')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
            </div>
            @if ($branches->count() > 1)
                <div class="col-12 col-md-4">
                    <label class="form-label" for="k-branch">{{ __('Works in the books of') }}</label>
                    <select class="form-select" id="k-branch" name="branch_id">
                        @foreach ($branches as $branch)<option value="{{ $branch->id }}">{{ $branch->name }}</option>@endforeach
                    </select>
                </div>
            @endif
            <div class="col-12 col-md-4">
                <div class="form-check"><input type="hidden" name="can_write" value="0"><input type="checkbox" class="form-check-input" id="k-write" name="can_write" value="1" checked><label class="form-check-label" for="k-write">{{ __('May record money in and money out') }}</label></div>
            </div>
        </div>
    </div>
    <div class="card-footer d-flex justify-content-end"><button type="submit" class="btn btn-primary"><x-icon name="key" class="me-1" /> {{ __('Make a key') }}</button></div>
</form>

<div class="card">
    <div class="card-header"><h2 class="h5 mb-0">{{ __('For the programmer of the other system') }}</h2></div>
    <div class="card-body">
        <p class="text-muted">{{ __('Every call goes to the address below with the key in the header. Answers are JSON. For a customer or a supplier a balance above zero is what the business owes it; below zero, what it owes the business.') }}</p>
<pre dir="ltr" class="bg-light border rounded p-3 small mb-0" style="white-space:pre-wrap">Authorization: Bearer ksb_...
{{ $base }}

GET  /business                          name, branch, currencies, account categories
GET  /rates                             rate of every currency against the base currency
GET  /accounts?search=&category=&kind=  accounts with their balances (100 a page, &page=2)
GET  /accounts/{id}                     one account
POST /accounts                          name, phone, address, category     (a new customer or supplier)
GET  /accounts/{id}/statement           ?currency=USD&from=2026-01-01&to=2026-01-31
POST /money-in                          account, currency, amount, date, note, reference, money, due
POST /money-out                         account, currency, amount, date, note, reference, money
GET  /papers/{reference}                a paper by its reference or its number
POST /papers/{reference}/cancel         reason

account    id of the customer or supplier        money   id of a cash box or bank (empty: the cash box)
reference  the number of the paper in your system: sent twice, it is recorded once

curl -H "Authorization: Bearer ksb_..." -d "account=12&currency=USD&amount=500&reference=SB-1001" {{ $base }}/money-in</pre>
    </div>
</div>
@endsection
