@extends('layouts.app', ['title' => __('Bank Reconciliation')])

@section('content')
<x-page-header :title="__('Bank Reconciliation')" icon="scale-balanced" :subtitle="__('Tick the lines you find on the statement of the bank. What is ticked should add up to the balance the bank shows.')" />

<div class="card">
    <div class="card-body">
        <form method="GET" class="filter-bar row g-2 align-items-end mb-3 no-print">
            <div class="col-12 col-md-3">
                <label class="form-label small mb-1" for="r-account">{{ __('Account') }}</label>
                <select class="form-select" id="r-account" name="account" required>
                    <option value="">{{ __('Choose…') }}</option>
                    @foreach ($accounts as $option)
                        <option value="{{ $option->id }}" @selected($account?->id === $option->id)>{{ $option->label() }} · {{ $option->currency->code }}</option>
                    @endforeach
                </select>
            </div>
            @include('partials.date-filter')
            <div class="col-6 col-md-2">
                <label class="form-label small mb-1" for="r-statement">{{ __('Balance on the statement') }}</label>
                <input type="text" inputmode="decimal" dir="ltr" class="form-control" id="r-statement" name="statement" value="{{ $statement }}">
            </div>
            <div class="col-6 col-md-auto">
                <label class="form-label small mb-1" for="r-show">{{ __('Lines') }}</label>
                <select class="form-select" id="r-show" name="show">
                    <option value="all" @selected($show === 'all')>{{ __('All') }}</option>
                    <option value="open" @selected($show === 'open')>{{ __('Not ticked') }}</option>
                </select>
            </div>
            <div class="col-auto"><button class="btn btn-light-secondary" type="submit">{{ __('Show') }}</button></div>
        </form>

        @if (! $account)
            <div class="empty-state">
                <div class="es-icon"><x-icon name="scale-balanced" /></div>
                <h2 class="h3">{{ __('Choose an account') }}</h2>
            </div>
        @else
            <div class="row g-2 mb-3">
                @foreach ([['In the books on :date', $figures['book']], ['Ticked (on the statement)', $figures['cleared']], ['Not ticked yet', $figures['open']]] as [$label, $amount])
                    <div class="col-6 col-md-3"><div class="border rounded p-2 h-100"><div class="small text-muted">{{ __($label, ['date' => fmt_date($to)]) }}</div><div class="fw-semibold" dir="ltr">{{ money($amount, $account->currency->code) }}</div></div></div>
                @endforeach
                @if ($figures['difference'] !== null)
                    <div class="col-6 col-md-3"><div class="border rounded p-2 h-100"><div class="small text-muted">{{ __('Statement less ticked') }}</div><div class="fw-semibold {{ \App\Support\Num::isZero($figures['difference']) ? 'text-success' : 'text-danger' }}" dir="ltr">{{ money($figures['difference'], $account->currency->code) }}</div></div></div>
                @endif
            </div>

            <form method="POST" action="{{ route('reconciliation.update') }}">
                @csrf
                @method('PUT')
                <input type="hidden" name="account" value="{{ $account->id }}">
                <table class="table table-hover w-100 keep-table">
                    <thead><tr><th class="no-print" style="width: 2.5rem"></th><th>{{ __('Date') }}</th><th>{{ __('No.') }}</th><th>{{ __('Details') }}</th><th class="text-end">{{ __('In') }}</th><th class="text-end">{{ __('Out') }}</th><th>{{ __('On the statement') }}</th></tr></thead>
                    <tbody>
                        @forelse ($lines as $line)
                            <tr>
                                <td class="no-print"><input type="hidden" name="shown[]" value="{{ $line->id }}"><input class="form-check-input" type="checkbox" name="cleared[]" value="{{ $line->id }}" @checked($line->is_cleared) @disabled(! $canManage) aria-label="{{ __('On the statement') }}"></td>
                                <td class="text-nowrap">{{ fmt_date($line->date) }}</td>
                                <td class="text-nowrap">{{ $line->entry?->number }}</td>
                                <td>{{ $line->memo ?: $line->entry?->description }}</td>
                                <td class="text-end text-nowrap">{{ \App\Support\Num::isZero($line->debit) ? '' : money($line->debit) }}</td>
                                <td class="text-end text-nowrap">{{ \App\Support\Num::isZero($line->credit) ? '' : money($line->credit) }}</td>
                                <td>{{ $line->is_cleared ? __('Yes') : __('No') }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="text-center text-muted">{{ __('Nothing here yet') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
                @if ($canManage && $lines->isNotEmpty())
                    <div class="d-flex justify-content-end no-print"><button type="submit" class="btn btn-primary"><x-icon name="check" class="me-1" /> {{ __('Save the ticks') }}</button></div>
                @endif
            </form>
        @endif
    </div>
</div>
@endsection
