@extends(request()->boolean('print') ? 'layouts.print' : 'layouts.app', ['title' => $invoice->number])

@section('content')
@unless (request()->boolean('print'))
    <x-page-header :title="$invoice->number" icon="file-invoice-dollar" :crumbs="[[__($about[1]), route('trade.index', ['kind' => $about[3]])], [$invoice->number]]">
        <a class="btn btn-outline-soft" href="{{ route('trade.show', ['kind' => $about[3], 'id' => $invoice->id, 'print' => 1]) }}" target="_blank" rel="noopener" data-no-soft><x-icon name="print" class="me-1" /> {{ __('Print') }}</a>
        @if ($canManage && ! $invoice->isCancelled())
            <form method="POST" action="{{ route('trade.destroy', ['kind' => $about[3], 'id' => $invoice->id]) }}" class="d-inline" data-confirm="{{ __('Cancel this? It stays on the list, crossed out.') }}" data-confirm-ok="{{ __('Cancel it') }}">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn btn-light-secondary">{{ __('Cancel it') }}</button>
            </form>
        @endif
    </x-page-header>
@endunless

<div class="card">
    <div class="card-body">
        <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3">
            <div><h2 class="h4 mb-1">{{ $business }}</h2><div class="text-muted">{{ __($about[0]) }}</div></div>
            <div class="text-end"><strong dir="ltr">{{ $invoice->number }}</strong><div class="small text-muted">{{ fmt_date($invoice->date) }}</div>@if ($invoice->due_date)<div class="small text-muted">{{ __('To be paid by') }}: {{ fmt_date($invoice->due_date) }}</div>@endif</div>
        </div>
        @if ($invoice->isCancelled())
            <div class="alert alert-danger py-2">{{ __('Cancelled') }}@if ($invoice->cancel_reason): {{ $invoice->cancel_reason }}@endif</div>
        @endif
        <p class="mb-3"><span class="text-muted">{{ in_array($invoice->kind, ['sale', 'sale_return']) ? __('Customer') : __('Supplier') }}:</span> <strong>{{ $invoice->party?->is_system ? __($invoice->party->name) : $invoice->party?->name }}</strong>@if ($invoice->party?->phone) · <span dir="ltr">{{ $invoice->party->phone }}</span>@endif</p>

        <table class="table table-bordered w-100 keep-table">
            <thead><tr><th>#</th><th>{{ __('Item') }}</th><th class="text-end">{{ __('Quantity') }}</th><th class="text-end">{{ __('Price') }}</th><th class="text-end">{{ __('Amount') }}</th></tr></thead>
            <tbody>
                @foreach ($invoice->lines as $n => $line)
                    <tr>
                        <td>{{ $n + 1 }}</td>
                        <td>{{ $line->item?->name }}@if ($line->description)<div class="small text-muted">{{ $line->description }}</div>@endif</td>
                        <td class="text-end text-nowrap">{{ qty($line->qty) }} {{ $line->item?->unit }}</td>
                        <td class="text-end text-nowrap">{{ money($line->price) }}</td>
                        <td class="text-end text-nowrap">{{ money($line->amount) }}</td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                @unless (\App\Support\Num::isZero($invoice->discount))
                    <tr><td colspan="4" class="text-end">{{ __('Subtotal') }}</td><td class="text-end text-nowrap">{{ money($invoice->subtotal) }}</td></tr>
                    <tr><td colspan="4" class="text-end">{{ __('Discount') }}</td><td class="text-end text-nowrap">{{ money($invoice->discount) }}</td></tr>
                @endunless
                <tr class="fw-semibold"><td colspan="4" class="text-end">{{ __('Total') }}</td><td class="text-end text-nowrap">{{ money($invoice->total, $invoice->currency->code) }}</td></tr>
                <tr><td colspan="4" class="text-end">{{ __('Paid') }}@if ($invoice->paymentVoucher) · <span dir="ltr">{{ $invoice->paymentVoucher->number }}</span>@endif</td><td class="text-end text-nowrap">{{ money($invoice->paid) }}</td></tr>
                <tr class="fw-semibold"><td colspan="4" class="text-end">{{ __('Left') }}</td><td class="text-end text-nowrap">{{ money(\App\Support\Num::sub($invoice->total, $invoice->paid), $invoice->currency->code) }}</td></tr>
            </tfoot>
        </table>
        @if ($invoice->warehouse && \App\Models\Warehouse::count() > 1)<p class="mb-2"><span class="text-muted">{{ __('Warehouse') }}:</span> {{ $invoice->warehouse->name }}</p>@endif
        @if ($invoice->costs->isNotEmpty() && ! request()->boolean('print'))
            @php($baseCode = \App\Models\Currency::base()->code)
            <h3 class="h6 mt-4">{{ __('Costs of this purchase') }}</h3>
            <table class="table table-bordered w-100 keep-table">
                <thead><tr><th>{{ __('Cost') }}</th><th>{{ __('Paid from') }}</th><th class="text-end">{{ __('Amount') }}</th><th class="text-end">{{ __('Value') }} {{ $baseCode }}</th></tr></thead>
                <tbody>
                    @foreach ($invoice->costs as $cost)
                        <tr><td>{{ $cost->name }}</td><td>{{ $cost->account?->label() }}</td><td class="text-end text-nowrap">{{ money($cost->amount, $cost->currency?->code) }}</td><td class="text-end text-nowrap">{{ money($cost->base_amount) }}</td></tr>
                    @endforeach
                </tbody>
                <tfoot><tr class="fw-semibold"><td colspan="3" class="text-end">{{ __('Total costs') }}</td><td class="text-end text-nowrap">{{ money($invoice->costs_total) }}</td></tr></tfoot>
            </table>
            <h3 class="h6 mt-4">{{ __('Cost of the goods after the costs') }}</h3>
            <table class="table table-bordered w-100 keep-table">
                <thead><tr><th>{{ __('Item') }}</th><th class="text-end">{{ __('Quantity') }}</th><th class="text-end">{{ __('Paid to the supplier') }} {{ $baseCode }}</th><th class="text-end">{{ __('Share of the costs') }}</th><th class="text-end">{{ __('Cost of one') }} {{ $baseCode }}</th></tr></thead>
                <tbody>
                    @foreach ($invoice->lines as $line)
                        @if ($line->item?->track_stock)
                            <tr>
                                <td>{{ $line->item->name }}</td>
                                <td class="text-end text-nowrap">{{ qty($line->qty) }} {{ $line->item->unit }}</td>
                                <td class="text-end text-nowrap">{{ money($line->base_amount) }}</td>
                                <td class="text-end text-nowrap">{{ money(\App\Support\Num::sub($line->base_cost, $line->base_amount)) }}</td>
                                <td class="text-end text-nowrap fw-semibold">{{ money(\App\Support\Num::div($line->base_cost, $line->qty)) }}</td>
                            </tr>
                        @endif
                    @endforeach
                </tbody>
            </table>
        @endif
        @if ($invoice->note)<p class="mb-0"><span class="text-muted">{{ __('Note') }}:</span> {{ $invoice->note }}</p>@endif
        @if (request()->boolean('print'))
            <div class="d-flex justify-content-between mt-5 small"><span>{{ __('Prepared by') }}: {{ $invoice->creator?->name }}</span><span>{{ __('Received by') }}: ____________</span><span>{{ __('Signature and stamp') }}: ____________</span></div>
        @endif
    </div>
</div>
@endsection
