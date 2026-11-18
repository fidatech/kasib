@extends('layouts.app', ['title' => __('Journal Entries')])

@section('content')
<x-page-header :title="__('Journal Entries')" icon="pen-to-square" :crumbs="[[__('Roznamcha'), route('journal.index')], [__('Journal Entries')]]" :subtitle="__('An entry written by hand, for a correction or for what the other pages do not cover.')" />

<form method="POST" action="{{ route('journal.store') }}" class="card mb-3">
    @csrf
    <div class="card-body">
        <div class="row g-3 mb-3">
            <div class="col-6 col-md-3">
                <label class="form-label">{{ __('Date') }} <span class="req">*</span></label>
                <x-date name="date" :value="old('date', today_iso())" :required="true" />
                @error('date')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
            </div>
            <div class="col-12 col-md-9">
                <label class="form-label" for="je-description">{{ __('Details') }} <span class="req">*</span></label>
                <input type="text" class="form-control @error('description') is-invalid @enderror" id="je-description" name="description" value="{{ old('description') }}" maxlength="500" required>
                @error('description')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
            </div>
        </div>
        <table class="table w-100 keep-table" data-rows>
            <thead><tr><th style="width:38%">{{ __('Account') }}</th><th class="text-end">{{ __('Debit') }}</th><th class="text-end">{{ __('Credit') }}</th><th>{{ __('Notes') }}</th></tr></thead>
            <tbody>
                @for ($i = 0; $i < $count; $i++)
                    <tr>
                        <td>
                            <select class="form-select" name="lines[{{ $i }}][account_id]" data-search aria-label="{{ __('Account') }}">
                                <option value="">{{ __('Choose…') }}</option>
                                @foreach ($accounts as $account)
                                    <option value="{{ $account->id }}" @selected((string) old("lines.$i.account_id") === (string) $account->id)>{{ $account->label() }} · {{ $account->currency->code }} · {{ $account->category->label() }}</option>
                                @endforeach
                            </select>
                        </td>
                        <td><input type="text" inputmode="decimal" dir="ltr" autocomplete="off" class="form-control text-end" name="lines[{{ $i }}][debit]" value="{{ old("lines.$i.debit") }}" aria-label="{{ __('Debit') }}"></td>
                        <td><input type="text" inputmode="decimal" dir="ltr" autocomplete="off" class="form-control text-end" name="lines[{{ $i }}][credit]" value="{{ old("lines.$i.credit") }}" aria-label="{{ __('Credit') }}"></td>
                        <td><input type="text" class="form-control" name="lines[{{ $i }}][memo]" value="{{ old("lines.$i.memo") }}" maxlength="500" aria-label="{{ __('Notes') }}"></td>
                    </tr>
                @endfor
            </tbody>
        </table>
        <div class="form-text">{{ __('Every amount is in the currency of its account. The debits and the credits must be equal in value; lines left empty are ignored.') }}</div>
    </div>
    <div class="card-footer d-flex gap-2 justify-content-end">
        <a class="btn btn-light-secondary" href="{{ route('journal.index') }}">{{ __('Cancel') }}</a>
        <button type="submit" class="btn btn-primary"><x-icon name="check" class="me-1" /> {{ __('Save') }}</button>
    </div>
</form>

@if ($recent->isNotEmpty())
    <div class="card">
        <div class="card-header"><h2 class="h5 card-title mb-0">{{ __('Latest entries written by hand') }}</h2></div>
        <div class="card-body">
            <table class="table table-hover w-100">
                <thead><tr><th>{{ __('No.') }}</th><th>{{ __('Date') }}</th><th>{{ __('Details') }}</th><th>{{ __('User') }}</th><th class="text-end no-print">{{ __('Actions') }}</th></tr></thead>
                <tbody>
                    @foreach ($recent as $entry)
                        @php $gone = $entry->isVoid() || $entry->reversed_by_id; @endphp
                        <tr class="{{ $gone ? 'text-decoration-line-through text-muted' : '' }}">
                            <td class="text-nowrap">{{ $entry->number }}</td>
                            <td class="text-nowrap">{{ fmt_date($entry->date) }}</td>
                            <td>{{ $entry->description }}</td>
                            <td>{{ $entry->creator?->name }}</td>
                            <td class="text-end text-nowrap no-print">
                                @unless ($gone)
                                    <form method="POST" action="{{ route('journal.destroy', ['id' => $entry->id]) }}" class="d-inline" data-confirm="{{ __('Cancel this? It stays on the list, crossed out.') }}" data-confirm-ok="{{ __('Cancel it') }}">
                                        @csrf
                                        @method('DELETE')
                                        <x-act kind="cancel" />
                                    </form>
                                @endunless
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endif
@endsection
