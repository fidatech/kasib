@extends('layouts.app', ['title' => $title])

@section('content')
@php
    $filtered = collect($filters ?? [])->contains(fn ($filter) => (string) $filter['value'] !== '');
@endphp
<x-page-header :title="$title" :icon="$icon">
    @isset($headerLinks)
        @foreach ($headerLinks as $link)
            <a class="btn btn-outline-soft" href="{{ $link['url'] }}">{{ $link['label'] }}</a>
        @endforeach
    @endisset
    @if ($canManage)
        @foreach ($headerForms ?? [] as $button)
            <form method="POST" action="{{ $button['url'] }}" class="d-inline">@csrf<button type="submit" class="btn btn-outline-soft">{{ $button['label'] }}</button></form>
        @endforeach
        <a class="btn btn-primary" href="{{ route($route.'.create', $routeParams) }}"><x-icon name="plus" class="me-1" /> {{ __('Add :name', ['name' => $singular]) }}</a>
    @endif
</x-page-header>

<div class="card">
    <div class="card-body">
        <form method="GET" class="filter-bar row g-2 mb-3">
            <div class="col-12 col-md">
                <input type="search" class="form-control" name="q" value="{{ $term }}" placeholder="{{ __('Search…') }}" aria-label="{{ __('Search') }}">
            </div>
            @foreach ($filters ?? [] as $filter)
                <div class="col col-md-auto">
                    <select class="form-select" name="{{ $filter['name'] }}" aria-label="{{ $filter['label'] }}" onchange="this.form.submit()">
                        <option value="">{{ $filter['label'] }}</option>
                        @foreach ($filter['options'] as $value => $text)
                            <option value="{{ $value }}" @selected((string) $filter['value'] === (string) $value)>{{ $text }}</option>
                        @endforeach
                    </select>
                </div>
            @endforeach
            <div class="col-auto">
                <button class="btn btn-light-secondary" type="submit" title="{{ __('Search') }}" aria-label="{{ __('Search') }}"><x-icon name="magnifying-glass" /></button>
            </div>
        </form>

        @if ($rows->isEmpty())
            <div class="empty-state">
                <div class="es-icon"><x-icon :name="$icon" /></div>
                <h2 class="h3">{{ $term !== '' || $filtered ? __('Nothing found') : __('Nothing here yet') }}</h2>
                @if ($canManage && $term === '' && ! $filtered)
                    <p>{{ __('Press "Add :name" to create the first one.', ['name' => $singular]) }}</p>
                @endif
            </div>
        @else
            <table class="table table-hover w-100 {{ $splitColumns ? 'table-split' : '' }}">
                <thead>
                    <tr>
                        @foreach (array_keys($columns) as $label)
                            <th>{{ __($label) }}</th>
                        @endforeach
                        <th class="text-end no-print">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                @unless ($splitColumns)<tbody>@endunless
                    @foreach ($rows as $row)
                        @php($lines = $splitColumns ? $split($row) : [])
                        @php($span = max(1, count($lines)))
                        @if ($splitColumns)<tbody class="split-group">@endif
                        <tr>
                            @foreach ($columns as $label => $column)
                                @if (in_array($label, $splitColumns, true))
                                    @include('crud.split-cell', ['cell' => $lines[0][$label] ?? null, 'label' => __($label)])
                                @else
                                    <td @if ($span > 1) rowspan="{{ $span }}" @endif @if ($splitColumns) data-label="{{ __($label) }}" data-keep-label @endif>{{ $column($row) }}</td>
                                @endif
                            @endforeach
                            <td class="text-end text-nowrap no-print" @if ($span > 1) rowspan="{{ $span }}" @endif @if ($splitColumns) data-label="" data-keep-label @endif>
                                <div class="act-group">
                                    @foreach ($links($row) as $link)
                                        @if (! empty($link['post']))
                                            <form method="POST" action="{{ $link['url'] }}" class="d-inline" @isset($link['confirm']) data-confirm="{{ $link['confirm'] }}" data-confirm-ok="{{ $link['label'] }}" @endisset>@csrf<button type="submit" class="btn btn-sm btn-light-secondary">{{ $link['label'] }}</button></form>
                                        @else
                                            <a class="btn btn-sm btn-light-secondary" href="{{ $link['url'] }}" @if (! empty($link['modal'])) data-modal @endif>{{ $link['label'] }}</a>
                                        @endif
                                    @endforeach
                                    @if ($canManage && $editable($row))
                                        <x-act kind="edit" :href="route($route.'.edit', $routeParams + ['id' => $row->getKey()])" />
                                        <form method="POST" action="{{ route($route.'.destroy', $routeParams + ['id' => $row->getKey()]) }}" class="d-inline" data-confirm="{{ __('Delete this?') }}" data-confirm-ok="{{ __('Delete') }}">
                                            @csrf
                                            @method('DELETE')
                                            <x-act kind="delete" />
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                        @foreach (array_slice($lines, 1) as $line)
                            <tr class="split-more">
                                @foreach ($splitColumns as $label)
                                    @include('crud.split-cell', ['cell' => $line[$label] ?? null, 'label' => __($label)])
                                @endforeach
                            </tr>
                        @endforeach
                        @if ($splitColumns)</tbody>@endif
                    @endforeach
                @unless ($splitColumns)</tbody>@endunless
            </table>
            {{ $rows->links('partials.pagination') }}
        @endif
    </div>
</div>
@endsection
