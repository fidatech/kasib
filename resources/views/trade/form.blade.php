@extends('layouts.app', ['title' => __($about[0])])

@section('content')
<x-page-header :title="__('New :name', ['name' => mb_strtolower(__($about[0]))])" icon="file-invoice-dollar" :crumbs="[[__($about[1]), route('trade.index', ['kind' => $about[3]])], [__('New')]]" />

<form method="POST" action="{{ route('trade.store', ['kind' => $about[3]]) }}" class="card" data-invoice data-base="{{ $base->id }}">
    @csrf
    <div class="card-body">
        @if ($items->isEmpty())
            <div class="alert alert-warning">{{ __('There are no items yet.') }} <a href="{{ route('items.create') }}">{{ __('Add :name', ['name' => __('Item')]) }}</a></div>
        @endif
        <div class="row g-3 mb-3">
            <div class="col-6 col-md-3">
                <label class="form-label">{{ __('Date') }} <span class="req">*</span></label>
                <x-date name="date" :value="old('date', today_iso())" :required="true" />
                @error('date')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
            </div>
            <div class="col-12 col-md-5">
                <label class="form-label" for="i-party">{{ $selling ? __('Customer') : __('Supplier') }} <span class="req">*</span></label>
                <select class="form-select @error('party_id') is-invalid @enderror" id="i-party" name="party_id" data-search required>
                    <option value="">{{ __('Choose…') }}</option>
                    @foreach ($parties as $party)
                        <option value="{{ $party->id }}" @selected((int) old('party_id', $selling ? $walkIn->id : 0) === $party->id)>{{ $party->is_system ? __($party->name) : $party->name.' · '.$party->group->label() }}</option>
                    @endforeach
                </select>
                @error('party_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label" for="i-currency">{{ __('Currency') }} <span class="req">*</span></label>
                <select class="form-select" id="i-currency" name="currency_id" required>
                    @foreach ($currencies as $currency)
                        <option value="{{ $currency->id }}" @selected((int) old('currency_id', $base->id) === $currency->id)>{{ $currency->code }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label">{{ __('To be paid by') }}</label>
                <x-date name="due_date" :value="old('due_date')" />
            </div>
        </div>

        @if ($warehouses->count() > 1)
            <div class="row g-3 mb-3">
                <div class="col-12 col-md-4">
                    <label class="form-label" for="i-warehouse">{{ $selling === ($kind !== 'sale_return') ? __('From the warehouse') : __('Into the warehouse') }}</label>
                    <select class="form-select" id="i-warehouse" name="warehouse_id">
                        @foreach ($warehouses as $warehouse)<option value="{{ $warehouse->id }}" @selected((int) old('warehouse_id', $warehouses->first()->id) === $warehouse->id)>{{ $warehouse->name }}</option>@endforeach
                    </select>
                </div>
            </div>
        @endif

        <div class="table-responsive">
            <table class="table w-100 keep-table" data-rows style="min-width: 40rem">
                <thead><tr><th style="width:38%">{{ __('Item') }}</th><th class="text-end" style="width:14%">{{ __('Quantity') }}</th><th class="text-end" style="width:18%">{{ __('Price') }}</th><th class="text-end" style="width:16%">{{ __('Amount') }}</th><th>{{ __('Notes') }}</th></tr></thead>
                <tbody>
                    @for ($i = 0; $i < $rows; $i++)
                        <tr data-line>
                            <td>
                                <select class="form-select" name="lines[{{ $i }}][item_id]" data-search data-item aria-label="{{ __('Item') }}">
                                    <option value="">{{ __('Choose…') }}</option>
                                    @foreach ($items as $item)
                                        <option value="{{ $item->id }}" data-price="{{ ($selling ? $item->sale_price : $item->cost_price) + 0 }}" @selected((string) old("lines.$i.item_id") === (string) $item->id)>{{ $item->label() }}@if ($item->track_stock) · {{ qty($item->stock) }} {{ $item->unit }}@endif</option>
                                    @endforeach
                                </select>
                            </td>
                            <td><input type="text" inputmode="decimal" dir="ltr" autocomplete="off" class="form-control text-end" name="lines[{{ $i }}][qty]" value="{{ old("lines.$i.qty") }}" data-qty aria-label="{{ __('Quantity') }}"></td>
                            <td><input type="text" inputmode="decimal" dir="ltr" autocomplete="off" class="form-control text-end" name="lines[{{ $i }}][price]" value="{{ old("lines.$i.price") }}" data-price aria-label="{{ __('Price') }}"></td>
                            <td class="text-end text-nowrap align-middle" dir="ltr" data-amount></td>
                            <td><input type="text" class="form-control" name="lines[{{ $i }}][description]" value="{{ old("lines.$i.description") }}" maxlength="255" aria-label="{{ __('Notes') }}"></td>
                        </tr>
                    @endfor
                </tbody>
            </table>
        </div>
        @error('lines')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
        <div class="form-text mb-3">{{ __('Rows left empty are ignored. A price is offered when the invoice is in the base currency; it can be changed.') }}</div>

        @if ($kind === 'purchase')
            <div class="card bg-body-tertiary border mb-3">
                <div class="card-body">
                    <h2 class="h6 mb-1">{{ __('Costs of this purchase') }}</h2>
                    <p class="form-text mt-0 mb-3">{{ __('Customs, transport, rent, porters: what was paid to bring the goods in. Each is paid from the account chosen, and all of them are shared over the goods by value, so the cost of one unit is counted after them.') }}</p>
                    <datalist id="cost-names">@foreach (\App\Models\InvoiceCost::NAMES as $name)<option value="{{ __($name) }}"></option>@endforeach</datalist>
                    <div class="table-responsive">
                        <table class="table w-100 keep-table mb-0" style="min-width: 34rem">
                            <thead><tr><th style="width:40%">{{ __('Cost') }}</th><th class="text-end" style="width:22%">{{ __('Amount') }}</th><th>{{ __('Paid from') }}</th></tr></thead>
                            <tbody>
                                @for ($i = 0; $i < 4; $i++)
                                    <tr>
                                        <td><input type="text" class="form-control" name="costs[{{ $i }}][name]" value="{{ old("costs.$i.name") }}" list="cost-names" maxlength="100" placeholder="{{ __(\App\Models\InvoiceCost::NAMES[$i]) }}" aria-label="{{ __('Cost') }}"></td>
                                        <td><input type="text" inputmode="decimal" dir="ltr" autocomplete="off" class="form-control text-end" name="costs[{{ $i }}][amount]" value="{{ old("costs.$i.amount") }}" aria-label="{{ __('Amount') }}"></td>
                                        <td>
                                            <select class="form-select" name="costs[{{ $i }}][account_id]" aria-label="{{ __('Paid from') }}">
                                                <option value="">{{ __('Choose…') }}</option>
                                                @foreach ($money as $account)<option value="{{ $account->id }}" @selected((int) old("costs.$i.account_id") === $account->id)>{{ $account->label() }} · {{ $account->currency->code }}</option>@endforeach
                                            </select>
                                        </td>
                                    </tr>
                                @endfor
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        @endif

        <div class="row g-3">
            <div class="col-12 col-md-5">
                <label class="form-label" for="i-note">{{ __('Note') }}</label>
                <input type="text" class="form-control" id="i-note" name="note" value="{{ old('note') }}" maxlength="500">
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label" for="i-discount">{{ __('Discount') }}</label>
                <input type="text" inputmode="decimal" dir="ltr" autocomplete="off" class="form-control text-end @error('discount') is-invalid @enderror" id="i-discount" name="discount" value="{{ old('discount') }}">
            </div>
            <div class="col-6 col-md-2">
                <div class="form-label">{{ __('Total') }}</div>
                <div class="form-control text-end fw-semibold" dir="ltr" data-total>0.00</div>
            </div>
            <div class="col-6 col-md-3">
                <label class="form-label" for="i-paid">{{ __('Paid now') }}</label>
                <div class="input-group">
                    <input type="text" inputmode="decimal" dir="ltr" autocomplete="off" class="form-control text-end @error('paid') is-invalid @enderror" id="i-paid" name="paid" value="{{ old('paid') }}">
                    <button type="button" class="btn btn-light-secondary" data-pay-all>{{ __('All') }}</button>
                </div>
                @error('paid')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
            </div>
            <div class="col-12 col-md-5">
                <label class="form-label" for="i-account">{{ $selling === ($kind !== 'sale_return') ? __('Received into') : __('Paid from') }}</label>
                <select class="form-select" id="i-account" name="account_id">
                    <option value="">{{ __('Choose…') }}</option>
                    @foreach ($money as $account)
                        <option value="{{ $account->id }}" @selected((int) old('account_id') === $account->id)>{{ $account->label() }} · {{ $account->currency->code }}</option>
                    @endforeach
                </select>
                <div class="form-text">{{ __('Needed only when something is paid now. What is left stays on the account.') }}</div>
            </div>
        </div>
    </div>
    <div class="card-footer d-flex gap-2 justify-content-end">
        <a class="btn btn-light-secondary" href="{{ route('trade.index', ['kind' => $about[3]]) }}">{{ __('Cancel') }}</a>
        <button type="submit" class="btn btn-primary"><x-icon name="check" class="me-1" /> {{ __('Save') }}</button>
    </div>
</form>
@endsection
