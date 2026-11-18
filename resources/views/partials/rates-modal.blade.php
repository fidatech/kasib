{{-- The exchange rates of the branch one works in, changed from the row under the navbar on any page (read only for those who may not change them). --}}
@php
    $rateBranch = current_branch();
    $rateBase = \App\Models\Currency::where('is_base', true)->first();
    $rateList = \App\Models\Currency::where('is_active', true)->where('is_base', false)->orderBy('code')->get();
    $rateRows = $rateBranch ? \App\Models\ExchangeRate::where('branch_id', $rateBranch->id)->whereIn('id', \App\Models\ExchangeRate::where('branch_id', $rateBranch->id)->selectRaw('max(id)')->groupBy('currency_id'))->get()->keyBy('currency_id') : collect();
    $rateShow = fn ($value) => $value === null ? '—' : rtrim(rtrim(number_format((float) $value, 4), '0'), '.');
    $rateEdit = auth()->user()->can('days.manage');
    $rateValue = fn ($value) => $value === null ? '' : rtrim(rtrim(number_format((float) $value, 6, '.', ''), '0'), '.');
@endphp
<div class="modal fade" id="ratesModal" tabindex="-1" aria-labelledby="ratesModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-scrollable">
        <form class="modal-content" method="POST" action="{{ route('rates.update') }}">
            @csrf
            @method('PUT')
            <input type="hidden" name="stay" value="1">
            <div class="modal-header">
                <div><h2 class="modal-title h5" id="ratesModalTitle">{{ __('Exchange Rates') }}</h2><div class="small text-muted">{{ fmt_date(today_iso()) }} · {{ __('in :currency', ['currency' => $rateBase?->code ?? 'AFN']) }}</div></div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('Close') }}"></button>
            </div>
            <div class="modal-body">
                @if ($rateList->isEmpty())
                    <p class="text-muted mb-0">{{ __('Nothing here yet') }}</p>
                @else
                    <table class="rates-table">
                        <thead><tr><th>{{ __('Currency') }}</th><th>{{ __('Rate') }}</th></tr></thead>
                        <tbody>
                            @foreach ($rateList as $currency)
                                @php $row = $rateRows[$currency->id] ?? null; @endphp
                                <tr>
                                    <th scope="row"><span class="dc-code">{{ $currency->code }}</span>@if ($currency->quote_unit > 1) <small dir="ltr">× {{ number_format($currency->quote_unit) }}</small>@endif<div class="small text-muted fw-normal">{{ __($currency->name) }}</div></th>
                                    @if ($rateEdit)
                                        <td><input type="text" inputmode="decimal" dir="ltr" autocomplete="off" class="form-control" name="rates[{{ $currency->id }}][rate]" value="{{ $rateValue($row?->rate) }}" aria-label="{{ __('Rate') }} {{ $currency->code }}"></td>
                                    @else
                                        <td dir="ltr"><strong>{{ $rateShow($row?->rate) }}</strong></td>
                                    @endif
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>
            <div class="modal-footer">
                @can('days.view')<a class="btn btn-light-secondary me-auto" href="{{ route('rates.index') }}">{{ __('Last changes') }}</a>@endcan
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">{{ __($rateEdit ? 'Cancel' : 'Close') }}</button>
                @if ($rateEdit && $rateList->isNotEmpty())<button type="submit" class="btn btn-primary"><x-icon name="check" class="me-1" /> {{ __('Save') }}</button>@endif
            </div>
        </form>
    </div>
</div>
