@extends('layouts.app', ['title' => __('Stock Moves')])

@section('content')
<x-page-header :title="__('Stock Moves')" icon="dolly" :subtitle="__('Goods taken from one warehouse to another. They keep their cost.')" />

@if ($canManage)
    @if ($warehouses->count() < 2)
        <div class="alert alert-info d-flex flex-wrap gap-2 align-items-center justify-content-between">
            <span>{{ __('There is one warehouse. Add a second one to move goods between them.') }}</span>
            <a class="btn btn-sm btn-primary" href="{{ route('warehouses.create') }}">{{ __('Add :name', ['name' => __('Warehouse')]) }}</a>
        </div>
    @else
        <form method="POST" action="{{ route('stock-moves.store') }}" class="card mb-3">
            @csrf
            <div class="card-header"><h2 class="h5 card-title mb-0">{{ __('Move goods') }}</h2></div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-6 col-md-2"><label class="form-label">{{ __('Date') }} <span class="req">*</span></label><x-date name="date" :value="old('date', today_iso())" :required="true" /></div>
                    <div class="col-12 col-md-4">
                        <label class="form-label" for="m-item">{{ __('Item') }} <span class="req">*</span></label>
                        <select class="form-select @error('item_id') is-invalid @enderror" id="m-item" name="item_id" data-search required>
                            <option value="">{{ __('Choose…') }}</option>
                            @foreach ($items as $item)<option value="{{ $item->id }}" @selected((int) old('item_id') === $item->id)>{{ $item->label() }} · {{ qty($item->stock) }} {{ $item->unit }}</option>@endforeach
                        </select>
                    </div>
                    <div class="col-6 col-md-2">
                        <label class="form-label" for="m-from">{{ __('From') }} <span class="req">*</span></label>
                        <select class="form-select" id="m-from" name="from_warehouse_id" required>
                            @foreach ($warehouses as $warehouse)<option value="{{ $warehouse->id }}" @selected((int) old('from_warehouse_id', $warehouses->first()->id) === $warehouse->id)>{{ $warehouse->name }}</option>@endforeach
                        </select>
                    </div>
                    <div class="col-6 col-md-2">
                        <label class="form-label" for="m-to">{{ __('To') }} <span class="req">*</span></label>
                        <select class="form-select @error('to_warehouse_id') is-invalid @enderror" id="m-to" name="to_warehouse_id" required>
                            @foreach ($warehouses as $warehouse)<option value="{{ $warehouse->id }}" @selected((int) old('to_warehouse_id', $warehouses->get(1)->id) === $warehouse->id)>{{ $warehouse->name }}</option>@endforeach
                        </select>
                        @error('to_warehouse_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-6 col-md-2">
                        <label class="form-label" for="m-qty">{{ __('Quantity') }} <span class="req">*</span></label>
                        <input type="text" inputmode="decimal" dir="ltr" autocomplete="off" class="form-control text-end @error('qty') is-invalid @enderror" id="m-qty" name="qty" value="{{ old('qty') }}" required>
                    </div>
                    <div class="col-12 col-md-10"><label class="form-label" for="m-note">{{ __('Note') }}</label><input type="text" class="form-control" id="m-note" name="note" value="{{ old('note') }}" maxlength="255"></div>
                    <div class="col-12 col-md-2 d-flex align-items-end"><button type="submit" class="btn btn-primary w-100"><x-icon name="dolly" class="me-1" /> {{ __('Move') }}</button></div>
                </div>
            </div>
        </form>
    @endif
@endif

<div class="card">
    <div class="card-body">
        @if ($moves->isEmpty())
            <div class="empty-state"><div class="es-icon"><x-icon name="dolly" /></div><h2 class="h3">{{ __('Nothing here yet') }}</h2></div>
        @else
            <table class="table table-hover w-100">
                <thead><tr><th>{{ __('No.') }}</th><th>{{ __('Date') }}</th><th>{{ __('Item') }}</th><th>{{ __('From') }}</th><th>{{ __('To') }}</th><th class="text-end">{{ __('Quantity') }}</th><th class="text-end">{{ __('Cost of one') }}</th><th>{{ __('Note') }}</th><th>{{ __('By') }}</th></tr></thead>
                <tbody>
                    @foreach ($moves as $move)
                        <tr>
                            <td class="text-nowrap" dir="ltr">{{ $move->number }}</td>
                            <td class="text-nowrap">{{ fmt_date($move->date) }}</td>
                            <td>{{ $move->item?->name }}</td>
                            <td>{{ $move->from?->name }}</td>
                            <td>{{ $move->to?->name }}</td>
                            <td class="text-end text-nowrap">{{ qty($move->qty) }} {{ $move->item?->unit }}</td>
                            <td class="text-end text-nowrap">{{ money($move->unit_cost) }}</td>
                            <td>{{ $move->note }}</td>
                            <td>{{ $move->creator?->name }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            {{ $moves->links('partials.pagination') }}
        @endif
    </div>
</div>
@endsection
