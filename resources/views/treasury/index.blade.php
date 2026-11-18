@extends(request()->boolean('print') ? 'layouts.print' : 'layouts.app', ['title' => __('Currency Position')])

@section('content')
<x-page-header :title="__('Currency Position')" icon="vault" :subtitle="__('The money of the business in every currency, and where it is.').' · '.fmt_date(today_iso())">
</x-page-header>

<div class="card">
    <div class="card-body">
        <table class="table table-hover w-100 keep-table">
            <thead>
                <tr>
                    <th>{{ __('Currency') }}</th>
                    @foreach ($groups as $group)
                        <th class="text-end">{{ $group->label() }}</th>
                    @endforeach
                    <th class="text-end">{{ __('Total') }}</th>
                    <th class="text-end">{{ __('Rate') }}</th>
                    <th class="text-end">{{ __('Value in :currency', ['currency' => $base]) }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($rows as $row)
                    <tr>
                        <td><strong>{{ $row['currency']->code }}</strong></td>
                        @foreach ($groups as $group)
                            <td class="text-end text-nowrap">{{ money($row['split'][$group->id] ?? 0) }}</td>
                        @endforeach
                        <td class="text-end text-nowrap fw-semibold">{{ money($row['balance']) }}</td>
                        <td class="text-end text-nowrap">{{ $row['rate'] === null ? '—' : \App\Support\Num::format($row['rate'], 6, true) }}</td>
                        <td class="text-end text-nowrap">{{ money($row['base']) }}</td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr class="fw-semibold"><td colspan="{{ $groups->count() + 3 }}">{{ __('Total') }}</td><td class="text-end text-nowrap">{{ money($worth, $base) }}</td></tr>
            </tfoot>
        </table>
    </div>
</div>
@endsection
