@extends('layouts.app', ['title' => __($about[1])])

@section('content')
<x-page-header :title="__($about[1])" icon="file-invoice-dollar">
    @if ($canManage)
        <a class="btn btn-primary" href="{{ route('trade.create', ['kind' => $about[3]]) }}"><x-icon name="plus" class="me-1" /> {{ __('New :name', ['name' => mb_strtolower(__($about[0]))]) }}</a>
    @endif
</x-page-header>

<div class="card">
    <div class="card-body">
        <form method="GET" class="filter-bar row g-2 align-items-end mb-3 no-print">
            <div class="col-12 col-md"><label class="form-label small mb-1" for="t-q">{{ __('Search') }}</label><input type="search" class="form-control" id="t-q" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="{{ __('No. or note') }}"></div>
            @include('partials.date-filter', ['from' => $filters['from'] ?? null, 'to' => $filters['to'] ?? null])
            <div class="col-6 col-md-auto">
                <label class="form-label small mb-1" for="t-party">{{ __('Account') }}</label>
                <select class="form-select" id="t-party" name="party" data-search>
                    <option value="">{{ __('All') }}</option>
                    @foreach ($parties as $party)
                        <option value="{{ $party->id }}" @selected((int) ($filters['party'] ?? 0) === $party->id)>{{ $party->is_system ? __($party->name) : $party->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-md-auto">
                <label class="form-label small mb-1" for="t-status">{{ __('Status') }}</label>
                <select class="form-select" id="t-status" name="status">
                    <option value="">{{ __('All') }}</option>
                    <option value="open" @selected(($filters['status'] ?? '') === 'open')>{{ __('In the books') }}</option>
                    <option value="cancelled" @selected(($filters['status'] ?? '') === 'cancelled')>{{ __('Cancelled') }}</option>
                </select>
            </div>
            <div class="col-auto"><button class="btn btn-light-secondary" type="submit">{{ __('Show') }}</button></div>
        </form>

        @if ($rows->isEmpty())
            <div class="empty-state"><div class="es-icon"><x-icon name="file-invoice-dollar" /></div><h2 class="h3">{{ __('Nothing here yet') }}</h2></div>
        @else
            <table class="table table-hover w-100">
                <thead><tr><th>{{ __('No.') }}</th><th>{{ __('Date') }}</th><th>{{ __('Account') }}</th><th class="text-end">{{ __('Total') }}</th><th class="text-end">{{ __('Paid') }}</th><th class="text-end">{{ __('Left') }}</th><th>{{ __('User') }}</th><th class="text-end no-print">{{ __('Actions') }}</th></tr></thead>
                <tbody>
                    @foreach ($rows as $row)
                        <tr class="{{ $row->isCancelled() ? 'text-decoration-line-through text-muted' : '' }}">
                            <td class="text-nowrap"><a href="{{ route('trade.show', ['kind' => $about[3], 'id' => $row->id]) }}">{{ $row->number }}</a></td>
                            <td class="text-nowrap">{{ fmt_date($row->date) }}</td>
                            <td>{{ $row->party?->is_system ? __($row->party->name) : $row->party?->name }}</td>
                            <td class="text-end text-nowrap">{{ money($row->total, $row->currency->code) }}</td>
                            <td class="text-end text-nowrap">{{ money($row->paid) }}</td>
                            <td class="text-end text-nowrap">{{ money(\App\Support\Num::sub($row->total, $row->paid)) }}</td>
                            <td>{{ $row->creator?->name }}</td>
                            <td class="text-end text-nowrap no-print">
                                <div class="act-group">
                                    <x-act kind="receipt" :href="route('trade.show', ['kind' => $about[3], 'id' => $row->id, 'print' => 1])" :label="__('Print')" blank />
                                    @if ($canManage && ! $row->isCancelled())
                                        <form method="POST" action="{{ route('trade.destroy', ['kind' => $about[3], 'id' => $row->id]) }}" class="d-inline" data-confirm="{{ __('Cancel this? It stays on the list, crossed out.') }}" data-confirm-ok="{{ __('Cancel it') }}">
                                            @csrf
                                            @method('DELETE')
                                            <x-act kind="cancel" />
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            {{ $rows->links() }}
        @endif
    </div>
</div>
@endsection
