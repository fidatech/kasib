@extends(request()->boolean('print') ? 'layouts.print' : 'layouts.app', ['title' => __('Trial Balance')])

@section('content')
<x-page-header :title="__('Trial Balance')" icon="scale-balanced" :crumbs="[[__('Reports'), route('reports.index')], [__('Trial Balance')]]" :subtitle="fmt_date(today_iso()).' · '.$base">
    <a class="btn btn-outline-soft" href="{{ request()->fullUrlWithQuery(['export' => 'xlsx']) }}"><x-icon name="file-excel" class="me-1" /> {{ __('Excel') }}</a>
    <a class="btn btn-outline-soft" href="{{ request()->fullUrlWithQuery(['print' => 1]) }}" target="_blank" rel="noopener"><x-icon name="print" class="me-1" /> {{ __('Print or PDF') }}</a>
</x-page-header>

<div class="card">
    <div class="card-body">
        @if ($groups->isEmpty())
            <div class="empty-state">
                <div class="es-icon"><x-icon name="scale-balanced" /></div>
                <h2 class="h3">{{ __('Nothing here yet') }}</h2>
            </div>
        @else
            <table class="table table-hover w-100 keep-table">
                <thead><tr><th>{{ __('Account') }}</th><th>{{ __('Group') }}</th><th class="text-end">{{ __('Balance') }}</th><th class="text-end">{{ __('Debit') }} {{ $base }}</th><th class="text-end">{{ __('Credit') }} {{ $base }}</th></tr></thead>
                <tbody>
                    @foreach ($natures as $nature => $heading)
                        @if (! empty($groups[$nature]))
                            <tr class="fw-semibold"><td colspan="5">{{ __($heading) }}</td></tr>
                            @foreach ($groups[$nature] as $row)
                                <tr>
                                    <td>{{ $row['account']->label() }}</td>
                                    <td>{{ $row['account']->category->label() }}</td>
                                    <td class="text-end text-nowrap">{{ money($row['account']->total->balance, $row['account']->currency->code) }}</td>
                                    <td class="text-end text-nowrap">{{ $row['debit'] === null ? '' : money($row['debit']) }}</td>
                                    <td class="text-end text-nowrap">{{ $row['credit'] === null ? '' : money($row['credit']) }}</td>
                                </tr>
                            @endforeach
                        @endif
                    @endforeach
                </tbody>
                <tfoot>
                    <tr class="fw-semibold"><td colspan="3">{{ __('Total') }}</td><td class="text-end text-nowrap">{{ money($debit) }}</td><td class="text-end text-nowrap">{{ money($credit) }}</td></tr>
                </tfoot>
            </table>
        @endif
    </div>
</div>
@endsection
