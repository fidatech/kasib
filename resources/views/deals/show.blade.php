@extends(request()->boolean('print') ? 'layouts.print' : 'layouts.app', ['title' => $deal->number.' '.$deal->name])

@section('content')
<x-page-header :title="$deal->name" icon="handshake" :crumbs="[[__('Deals'), route('deals.index')], [$deal->number]]" :subtitle="$deal->number.' · '.fmt_date($deal->opened_on).' · '.($deal->isOpen() ? __('Open') : __('Closed').' '.fmt_date($deal->closed_on))">
    @if ($canManage && $deal->isOpen())
        <form method="POST" action="{{ route('deals.close', ['id' => $deal->id]) }}" class="d-inline" data-confirm="{{ __('Close this deal? Its profit or loss goes to the books and nothing more is recorded on it.') }}" data-confirm-ok="{{ __('Close the deal') }}">
            @csrf
            <button type="submit" class="btn btn-primary"><x-icon name="check" class="me-1" /> {{ __('Close the deal') }}</button>
        </form>
    @endif
</x-page-header>

<div class="row g-3 mb-3">
    <div class="col-12 col-md-4"><div class="card h-100"><div class="card-body"><div class="small text-muted">{{ __('Cost') }} · {{ $base }}</div><div class="h4 mb-0 text-nowrap" dir="ltr">{{ money($figures['cost']) }}</div></div></div></div>
    <div class="col-12 col-md-4"><div class="card h-100"><div class="card-body"><div class="small text-muted">{{ __('Received') }} · {{ $base }}</div><div class="h4 mb-0 text-nowrap" dir="ltr">{{ money($figures['received']) }}</div></div></div></div>
    <div class="col-12 col-md-4"><div class="card h-100"><div class="card-body"><div class="small text-muted">{{ $deal->isOpen() ? __('Profit or loss so far') : __('Profit or loss') }} · {{ $base }}</div><div class="h4 mb-0 text-nowrap" dir="ltr">{{ money($deal->isOpen() ? $figures['result'] : $deal->result) }}</div></div></div></div>
</div>
@if ($deal->notes)<p class="text-muted">{{ $deal->notes }}</p>@endif

@if ($canManage && $deal->isOpen())
    <form method="POST" action="{{ route('deals.move', ['id' => $deal->id]) }}" class="card mb-3 no-print">
        @csrf
        <div class="card-body">
            <div class="row g-2 align-items-end">
                <div class="col-6 col-md-2">
                    <label class="form-label" for="m-kind">{{ __('Kind') }}</label>
                    <select class="form-select" id="m-kind" name="kind" data-plain>
                        <option value="cost" @selected(old('kind') !== 'receipt')>{{ __('Paid for the deal') }}</option>
                        <option value="receipt" @selected(old('kind') === 'receipt')>{{ __('Received from the deal') }}</option>
                    </select>
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label">{{ __('Date') }}</label>
                    <x-date name="date" :value="old('date', today_iso())" :required="true" />
                </div>
                <div class="col-12 col-md-3">
                    <label class="form-label" for="m-account">{{ __('Cash, bank or saraf') }}</label>
                    <select class="form-select" id="m-account" name="account_id" required>
                        @foreach ($money as $id => $label)
                            <option value="{{ $id }}" @selected((string) old('account_id', $cash) === (string) $id)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label" for="m-amount">{{ __('Amount') }}</label>
                    <input type="text" inputmode="decimal" dir="ltr" autocomplete="off" class="form-control @error('amount') is-invalid @enderror" id="m-amount" name="amount" value="{{ old('amount') }}" required>
                </div>
                <div class="col-12 col-md">
                    <label class="form-label" for="m-details">{{ __('Details') }}</label>
                    <input type="text" class="form-control" id="m-details" name="description" value="{{ old('description') }}" maxlength="500">
                </div>
                <div class="col-auto"><button type="submit" class="btn btn-primary"><x-icon name="check" class="me-1" /> {{ __('Save') }}</button></div>
            </div>
            @foreach (['date', 'amount', 'account_id'] as $box)
                @error($box)<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
            @endforeach
        </div>
    </form>
@endif

<div class="card">
    <div class="card-body">
        @if ($papers->isEmpty())
            <div class="empty-state">
                <div class="es-icon"><x-icon name="handshake" /></div>
                <h2 class="h3">{{ __('Nothing here yet') }}</h2>
                <p>{{ __('Record what was paid for the deal, then what came in from it.') }}</p>
            </div>
        @else
            <table class="table table-hover w-100">
                <thead><tr><th>{{ __('No.') }}</th><th>{{ __('Date') }}</th><th>{{ __('Kind') }}</th><th>{{ __('Details') }}</th><th>{{ __('Cash, bank or saraf') }}</th><th class="text-end">{{ __('Amount') }}</th><th class="text-end">{{ __('Value in :currency', ['currency' => $base]) }}</th><th>{{ __('User') }}</th><th class="text-end no-print">{{ __('Actions') }}</th></tr></thead>
                <tbody>
                    @foreach ($papers as $paper)
                        <tr class="{{ $paper->isCancelled() ? 'text-decoration-line-through text-muted' : '' }}">
                            <td class="text-nowrap">{{ $paper->number }}</td>
                            <td class="text-nowrap">{{ fmt_date($paper->date) }}</td>
                            <td>{{ $paper->kind === 'deal_cost' ? __('Paid for the deal') : __('Received from the deal') }}</td>
                            <td>{{ $paper->description }}</td>
                            <td>{{ $paper->account->label() }}</td>
                            <td class="text-end text-nowrap">{{ money($paper->amount, $paper->currency->code) }}</td>
                            <td class="text-end text-nowrap">{{ money($paper->base_amount) }}</td>
                            <td>{{ $paper->creator?->name }}</td>
                            <td class="text-end text-nowrap no-print">
                                @if ($canManage && $deal->isOpen() && ! $paper->isCancelled())
                                    <form method="POST" action="{{ route('deals.cancel', ['id' => $deal->id, 'paper' => $paper->id]) }}" class="d-inline" data-confirm="{{ __('Cancel this? It stays on the list, crossed out.') }}" data-confirm-ok="{{ __('Cancel it') }}">
                                        @csrf
                                        @method('DELETE')
                                        <x-act kind="cancel" />
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
</div>
@endsection
