{{-- The rate boxes of every foreign currency in use. $rows from DayController::rows(). --}}
<table class="table w-100 keep-table">
    <thead>
        <tr>
            <th>{{ __('Currency') }}</th>
            <th class="text-end">{{ __('Cash and bank') }}</th>
            <th>{{ __('Rate in AFN') }}</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($rows as $row)
            @php
                $id = $row['currency']->id;
                $shown = fn (?string $value) => $value === null ? '' : \App\Support\Num::format($value, 6, true);
            @endphp
            <tr>
                <td><strong>{{ $row['currency']->code }}</strong><div class="small text-muted">{{ __('for') }} {{ number_format($row['currency']->quote_unit) }} {{ $row['currency']->code }}</div></td>
                <td class="text-end text-nowrap">{{ money($row['balance']) }}</td>
                <td><input type="text" inputmode="decimal" dir="ltr" autocomplete="off" class="form-control @error('rates.'.$id.'.rate') is-invalid @enderror" name="rates[{{ $id }}][rate]" value="{{ old('rates.'.$id.'.rate', $shown($row['rate']?->rate)) }}" aria-label="{{ __('Rate in AFN') }} {{ $row['currency']->code }}"></td>
            </tr>
        @endforeach
    </tbody>
</table>
