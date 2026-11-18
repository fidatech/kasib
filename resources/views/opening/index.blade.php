@extends('layouts.app', ['title' => __('Opening Balances')])

@section('content')
<x-page-header :title="__('Opening Balances')" icon="file-import" :subtitle="__('Bring the accounts of the business and what each one starts with from an Excel or CSV file.')">
    <a class="btn btn-outline-soft" href="{{ route('opening.template') }}" data-no-soft><x-icon name="download" class="me-1" /> {{ __('Download the empty sheet') }}</a>
</x-page-header>

@if ($canManage)
    <form method="POST" action="{{ route('opening.store') }}" enctype="multipart/form-data" class="card mb-3">
        @csrf
        <div class="card-body">
            <div class="row g-3 align-items-end">
                <div class="col-12 col-md-7">
                    <label class="form-label" for="ob-file">{{ __('Excel (.xlsx) or CSV file') }} <span class="req">*</span></label>
                    <input type="file" class="form-control @error('file') is-invalid @enderror" id="ob-file" name="file" accept=".xlsx,.csv" required>
                    @error('file')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                </div>
                <div class="col-auto"><button type="submit" class="btn btn-primary"><x-icon name="check" class="me-1" /> {{ __('Bring in') }}</button></div>
            </div>
            <div class="form-text mt-3">{{ __('Columns: Category, Name, Phone, Currency, Balance, Side. In "Side" write "we owe" or "owes us" for customers and suppliers; leave it empty for cash, bank and saraf. An account that is already there gets the balance; a new one is made. A file with a mistake in any row changes nothing.') }}</div>
        </div>
    </form>
@endif

<div class="card">
    <div class="card-header"><h2 class="h5 card-title mb-0">{{ __('Opening balances recorded') }}</h2></div>
    <div class="card-body">
        @if ($entries->isEmpty())
            <div class="empty-state">
                <div class="es-icon"><x-icon name="file-import" /></div>
                <h2 class="h3">{{ __('Nothing here yet') }}</h2>
            </div>
        @else
            <table class="table table-hover w-100">
                <thead><tr><th>{{ __('No.') }}</th><th>{{ __('Date') }}</th><th>{{ __('Details') }}</th><th class="text-end">{{ __('Amount') }}</th><th>{{ __('User') }}</th></tr></thead>
                <tbody>
                    @foreach ($entries as $entry)
                        @php $line = $entry->lines->first(); @endphp
                        <tr class="{{ $entry->isVoid() || $entry->reversed_by_id ? 'text-decoration-line-through text-muted' : '' }}">
                            <td class="text-nowrap">{{ $entry->number }}</td>
                            <td class="text-nowrap">{{ fmt_date($entry->date) }}</td>
                            <td>{{ $entry->description }}</td>
                            <td class="text-end text-nowrap">@if ($line){{ money(\App\Support\Num::isZero($line->debit) ? $line->credit : $line->debit, $line->account->currency->code) }}@endif</td>
                            <td>{{ $entry->creator?->name }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            {{ $entries->links('partials.pagination') }}
        @endif
    </div>
</div>
@endsection
