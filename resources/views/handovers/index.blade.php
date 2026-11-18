@extends('layouts.app', ['title' => __('Cash Boxes & Handover')])

@section('content')
<x-page-header :title="__('Cash Boxes & Handover')" icon="vault" :subtitle="__('Every cashier works from his own box. At the end of the shift he counts it and hands it over.')" />

@if ($canManage && $from && $boxes->count() > 1)
    <form method="POST" action="{{ route('handovers.store') }}" class="card mb-3">
        @csrf
        <div class="card-header"><h2 class="h5 mb-0">{{ __('Hand over a box') }}</h2></div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-12 col-md-4">
                    <label class="form-label" for="h-from">{{ __('From the box') }}</label>
                    <select class="form-select" id="h-from" name="from" onchange="location.href='{{ route('handovers.index') }}?box=' + this.value">
                        @foreach ($boxes as $box)
                            <option value="{{ $box->id }}" @selected($from->id === $box->id)>{{ $box->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-12 col-md-4">
                    <label class="form-label" for="h-to">{{ __('To the box') }}</label>
                    <select class="form-select @error('to') is-invalid @enderror" id="h-to" name="to" required>
                        @foreach ($boxes->where('id', '!=', $from->id) as $box)
                            <option value="{{ $box->id }}" @selected((int) old('to') === $box->id)>{{ $box->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-12 col-md-4">
                    <label class="form-label" for="h-note">{{ __('Note') }}</label>
                    <input type="text" class="form-control" id="h-note" name="note" value="{{ old('note') }}" maxlength="500">
                </div>
                @forelse ($balances as $account)
                    <div class="col-12 col-md-4">
                        <label class="form-label" for="h-c-{{ $account->id }}">{{ __('Counted, :currency', ['currency' => $account->currency->code]) }}</label>
                        <input type="text" inputmode="decimal" dir="ltr" class="form-control" id="h-c-{{ $account->id }}" name="counted[{{ $account->id }}]" value="{{ old('counted.'.$account->id) }}">
                        <div class="form-text">{{ __('In the books: :amount', ['amount' => money($account->total?->balance ?? 0)]) }}</div>
                    </div>
                @empty
                    <div class="col-12 text-muted">{{ __('This box is empty.') }}</div>
                @endforelse
                @error('counted')<div class="col-12"><div class="invalid-feedback d-block">{{ $message }}</div></div>@enderror
            </div>
        </div>
        @if ($balances->isNotEmpty())
            <div class="card-footer d-flex justify-content-end"><button type="submit" class="btn btn-primary"><x-icon name="check" class="me-1" /> {{ __('Hand over') }}</button></div>
        @endif
    </form>
@endif

<div class="card mb-3">
    <div class="card-header"><h2 class="h5 mb-0">{{ __('Handovers') }}</h2></div>
    <div class="card-body">
        @if ($handovers->isEmpty())
            <div class="empty-state"><div class="es-icon"><x-icon name="vault" /></div><h2 class="h3">{{ __('Nothing here yet') }}</h2></div>
        @else
            <table class="table table-hover w-100 keep-table">
                <thead><tr><th>{{ __('Date') }}</th><th>{{ __('From the box') }}</th><th>{{ __('To the box') }}</th><th>{{ __('Counted') }}</th><th>{{ __('Difference from the books') }}</th><th>{{ __('Handed over by') }}</th><th>{{ __('Accepted by') }}</th></tr></thead>
                <tbody>
                    @foreach ($handovers as $handover)
                        <tr>
                            <td class="text-nowrap">{{ fmt_date($handover->date) }}</td>
                            <td>{{ $handover->from?->name }}</td>
                            <td>{{ $handover->to?->name }}</td>
                            <td>{{ collect($handover->lines)->map(fn ($l) => money($l['counted'], $l['currency']))->implode(', ') }}</td>
                            <td>{{ collect($handover->lines)->reject(fn ($l) => \App\Support\Num::isZero($l['difference']))->map(fn ($l) => money($l['difference'], $l['currency']))->implode(', ') ?: '—' }}</td>
                            <td>{{ $handover->user?->name }}@if ($handover->note)<div class="small text-muted">{{ $handover->note }}</div>@endif</td>
                            <td>
                                @if ($handover->accepted_at)
                                    {{ $handover->acceptedBy?->name }}
                                @elseif ($canManage)
                                    <form method="POST" action="{{ route('handovers.accept', ['id' => $handover->id]) }}" class="d-inline no-print">@csrf<button type="submit" class="btn btn-sm btn-light-secondary">{{ __('I received it') }}</button></form>
                                @else
                                    —
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            {{ $handovers->links() }}
        @endif
    </div>
</div>

@if ($canAssign)
    <form method="POST" action="{{ route('handovers.assign') }}" class="card">
        @csrf
        @method('PUT')
        <div class="card-header"><h2 class="h5 mb-0">{{ __('Who holds which box') }}</h2></div>
        <div class="card-body">
            <div class="row g-3">
                @foreach ($boxes as $box)
                    <div class="col-12 col-md-4">
                        <label class="form-label" for="h-u-{{ $box->id }}">{{ $box->name }}</label>
                        <select class="form-select" id="h-u-{{ $box->id }}" name="holder[{{ $box->id }}]">
                            <option value="">{{ __('Nobody (a shared box)') }}</option>
                            @foreach ($users as $user)
                                <option value="{{ $user->id }}" @selected((int) $box->user_id === $user->id)>{{ $user->name }}</option>
                            @endforeach
                        </select>
                    </div>
                @endforeach
                <div class="col-12"><div class="form-text">{{ __('A new box is added under Accounts, Cash. The box of a user comes first in his forms.') }}</div></div>
            </div>
        </div>
        <div class="card-footer d-flex justify-content-end"><button type="submit" class="btn btn-primary"><x-icon name="check" class="me-1" /> {{ __('Save') }}</button></div>
    </form>
@endif
@endsection
