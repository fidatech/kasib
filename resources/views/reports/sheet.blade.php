@extends(request()->boolean('print') ? 'layouts.print' : 'layouts.app', ['title' => $title])

{{-- One table for every report: headings, lines, totals. Cells may be text or ['text' => .., 'url' => ..]. --}}
@section('content')
<x-page-header :title="$title" :icon="$icon" :crumbs="[[__('Reports'), route('reports.index')], [$title]]" :subtitle="trim(($from ? fmt_date($from).' – ' : '').($to ? fmt_date($to) : ''))">
</x-page-header>

<div class="card">
    <div class="card-body">
        @isset($note)<div class="alert alert-warning">{{ $note }}</div>@endisset
        @if ($from || $to || $filters || ! empty($inputs))
            <form method="GET" class="filter-bar row g-2 align-items-end mb-3 no-print">
                @if ($from)
                    @include('partials.date-filter')
                @elseif ($to)
                    <div class="col-6 col-md-auto">
                        <label class="form-label small mb-1">{{ __('On the date') }}</label>
                        <x-date name="to" :value="$to" />
                    </div>
                @endif
                @foreach ($filters as $filter)
                    <div class="col-6 col-md-auto">
                        <label class="form-label small mb-1" for="rp-{{ $filter['name'] }}">{{ __($filter['label']) }}</label>
                        <select class="form-select" id="rp-{{ $filter['name'] }}" name="{{ $filter['name'] }}">
                            @unless (array_key_exists('value', $filter))<option value="">{{ __('All') }}</option>@endunless
                            @foreach ($filter['options'] as $value => $text)
                                <option value="{{ $value }}" @selected((string) ($filter['value'] ?? request()->query($filter['name'])) === (string) $value)>{{ $text }}</option>
                            @endforeach
                        </select>
                    </div>
                @endforeach
                @foreach ($inputs ?? [] as $input)
                    <div class="col-6 col-md-auto">
                        <label class="form-label small mb-1" for="rp-{{ $input['name'] }}">{{ $input['label'] }}</label>
                        <input type="text" inputmode="decimal" dir="ltr" class="form-control" id="rp-{{ $input['name'] }}" name="{{ $input['name'] }}" value="{{ $input['value'] }}">
                    </div>
                @endforeach
                <div class="col-auto"><button class="btn btn-light-secondary" type="submit">{{ __('Show') }}</button></div>
            </form>
        @endif

        @if (! collect($rows)->contains(fn ($row) => isset($row['cells']) && empty($row['total'])))
            <div class="empty-state">
                <div class="es-icon"><x-icon :name="$icon" /></div>
                <h2 class="h3">{{ __('Nothing here yet') }}</h2>
            </div>
        @else
            <table class="table table-hover w-100 keep-table">
                <thead>
                    <tr>
                        @foreach ($columns as $column)
                            <th class="{{ ($column[1] ?? '') === 'end' ? 'text-end' : '' }}">{{ $column[0] === '' ? '' : __($column[0]) }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach ($rows as $row)
                        @isset($row['heading'])
                            <tr class="table-light fw-semibold"><td colspan="{{ count($columns) }}">{{ $row['heading'] }}</td></tr>
                        @else
                            <tr class="{{ empty($row['total']) ? '' : 'fw-semibold' }}">
                                @foreach ($row['cells'] as $i => $cell)
                                    <td class="{{ ($columns[$i][1] ?? '') === 'end' ? 'text-end text-nowrap' : '' }}">@if (is_array($cell))<a href="{{ $cell['url'] }}">{{ $cell['text'] }}</a>@else{{ $cell }}@endif</td>
                                @endforeach
                            </tr>
                        @endisset
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
</div>
@endsection
