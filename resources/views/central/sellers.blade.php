@extends('layouts.central', ['title' => __('Sellers and referrals')])

@section('content')
<x-page-header :title="__('Sellers and referrals')" icon="handshake" :subtitle="__('Who sells the system, what each one earned, and the rewards of clients who brought other clients.')" />

<div class="card mb-3">
    <div class="card-header"><h2 class="h5 card-title mb-0">{{ __('Sellers') }}</h2></div>
    <div class="card-body">
        <table class="table w-100 keep-table">
            <thead><tr><th>{{ __('Name') }}</th><th>{{ __('Code') }}</th><th>{{ __('Phone') }}</th><th>{{ __('Email') }}</th><th>{{ __('First year %') }}</th><th>{{ __('Renewal %') }}</th><th>{{ __('Clients') }}</th><th>{{ __('Active') }}</th><th></th></tr></thead>
            <tbody>
                @foreach ($sellers as $seller)
                    <tr>
                        <form method="POST" action="{{ route('admin.sellers.update', ['id' => $seller->id]) }}">
                            @csrf
                            @method('PUT')
                            <td><input class="form-control form-control-sm" name="name" value="{{ $seller->name }}" required aria-label="{{ __('Name') }}"></td>
                            <td><input class="form-control form-control-sm" name="code" value="{{ $seller->code }}" dir="ltr" required aria-label="{{ __('Code') }}"></td>
                            <td><input class="form-control form-control-sm" name="phone" value="{{ $seller->phone }}" dir="ltr" aria-label="{{ __('Phone') }}"></td>
                            <td><input class="form-control form-control-sm" type="email" name="email" value="{{ $seller->email }}" dir="ltr" aria-label="{{ __('Email') }}"></td>
                            <td><input class="form-control form-control-sm" name="first_percent" value="{{ $seller->first_percent + 0 }}" dir="ltr" style="width: 5rem" aria-label="{{ __('First year %') }}"></td>
                            <td><input class="form-control form-control-sm" name="renewal_percent" value="{{ $seller->renewal_percent + 0 }}" dir="ltr" style="width: 5rem" aria-label="{{ __('Renewal %') }}"></td>
                            <td>{{ $seller->tenants_count }}</td>
                            <td><input class="form-check-input" type="checkbox" name="is_active" value="1" @checked($seller->is_active) aria-label="{{ __('Active') }}"></td>
                            <td class="text-end"><button type="submit" class="btn btn-sm btn-light-secondary">{{ __('Save') }}</button></td>
                        </form>
                    </tr>
                @endforeach
                <tr>
                    <form method="POST" action="{{ route('admin.sellers.store') }}">
                        @csrf
                        <td><input class="form-control form-control-sm" name="name" value="{{ old('name') }}" placeholder="{{ __('Name') }}" required></td>
                        <td><input class="form-control form-control-sm @error('code') is-invalid @enderror" name="code" value="{{ old('code') }}" dir="ltr" placeholder="ahmad" required></td>
                        <td><input class="form-control form-control-sm" name="phone" value="{{ old('phone') }}" dir="ltr"></td>
                        <td><input class="form-control form-control-sm" type="email" name="email" value="{{ old('email') }}" dir="ltr"></td>
                        <td><input class="form-control form-control-sm" name="first_percent" value="{{ old('first_percent', 0) }}" dir="ltr" style="width: 5rem"></td>
                        <td><input class="form-control form-control-sm" name="renewal_percent" value="{{ old('renewal_percent', 0) }}" dir="ltr" style="width: 5rem"></td>
                        <td colspan="2"></td>
                        <td class="text-end"><button type="submit" class="btn btn-sm btn-primary">{{ __('Add') }}</button></td>
                    </form>
                </tr>
            </tbody>
        </table>
        <div class="form-text">{{ __('A client that registers with the link of a seller (:link) is his. A business can also be given to a seller on its own page, by his code.', ['link' => url('/register').'?ref=code']) }}</div>
    </div>
</div>

<form method="POST" action="{{ route('admin.sellers.reward') }}" class="card mb-3">
    @csrf
    <div class="card-body d-flex flex-wrap align-items-end gap-3">
        <div>
            <label class="form-label" for="r-reward">{{ __('Reward for referring a client (US dollars)') }}</label>
            <div class="input-group" style="max-width: 220px"><span class="input-group-text">$</span><input type="text" inputmode="decimal" dir="ltr" class="form-control" id="r-reward" name="referral_reward" value="{{ old('referral_reward', $reward + 0) }}"></div>
        </div>
        <button type="submit" class="btn btn-light-secondary">{{ __('Save') }}</button>
        <div class="form-text w-100">{{ __('Earned once, when the business that was referred pays for the first time. 0: no reward. Every business has its own link on its licence page.') }}</div>
    </div>
</form>

<div class="card">
    <div class="card-header d-flex flex-wrap justify-content-between gap-2">
        <h2 class="h5 card-title mb-0">{{ __('Commissions and rewards') }} · <span dir="ltr">${{ money($owed) }}</span> {{ __('to pay') }}</h2>
        <div class="seg"><a href="{{ route('admin.sellers.index') }}" class="{{ $open ? 'active' : '' }}">{{ __('To pay') }}</a><a href="{{ route('admin.sellers.index', ['show' => 'paid']) }}" class="{{ $open ? '' : 'active' }}">{{ __('Paid') }}</a></div>
    </div>
    <div class="card-body">
        <table class="table table-hover w-100">
            <thead><tr><th>{{ __('Date') }}</th><th>{{ __('Earned by') }}</th><th>{{ __('Business that paid') }}</th><th>{{ __('Payment') }}</th><th class="text-end">%</th><th class="text-end">{{ __('Amount') }}</th><th class="text-end">{{ __('Actions') }}</th></tr></thead>
            <tbody>
                @forelse ($commissions as $row)
                    <tr>
                        <td class="text-nowrap">{{ $row->created_at?->toDateString() }}</td>
                        <td>{{ $row->kind === 'seller' ? $row->seller?->name : $row->referrer?->name }} <span class="badge bg-secondary-subtle text-secondary">{{ $row->kind === 'seller' ? __('Seller') : __('Referral') }}</span></td>
                        <td>{{ $row->tenant?->name }}</td>
                        <td dir="ltr">{{ $row->payment?->number }} · ${{ money($row->payment?->amount ?? 0) }}</td>
                        <td class="text-end">{{ $row->percent === null ? '' : $row->percent + 0 }}</td>
                        <td class="text-end" dir="ltr">${{ money($row->amount) }}</td>
                        <td class="text-end">
                            @if ($row->paid_on)
                                {{ $row->paid_on->toDateString() }}
                            @else
                                <form method="POST" action="{{ route('admin.sellers.paid', ['id' => $row->id]) }}" class="d-inline">@csrf<button type="submit" class="btn btn-sm btn-light-secondary">{{ __('Mark as paid') }}</button></form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center text-muted py-4">{{ __('Nothing here yet') }}</td></tr>
                @endforelse
            </tbody>
        </table>
        {{ $commissions->links() }}
    </div>
</div>
@endsection
