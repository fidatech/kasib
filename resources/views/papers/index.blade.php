@extends(request()->boolean('print') ? 'layouts.print' : 'layouts.app', ['title' => $title])

@section('content')
<x-page-header :title="$title" :icon="$icon" :subtitle="trim(($branch?->name ?? '').($hint ? ' · '.__($hint) : ''), ' ·')">
    @if ($canManage)
        <a class="btn btn-primary" href="{{ route($route.'.create') }}"><x-icon name="plus" class="me-1" /> {{ __('Add :name', ['name' => $singular]) }}</a>
    @endif
</x-page-header>

<div class="card">
    <div class="card-body">
        @if (! empty($filters))
            {{-- every paper can be found by its date, its accounts, its currency, its user, its amount and its words --}}
            <form method="GET" class="row g-2 align-items-end mb-3 no-print">
                @include('partials.date-filter', ['from' => request()->query('from'), 'to' => request()->query('to')])
                @foreach ($filters as $filter)
                    <div class="col-6 col-md-auto">
                        <label class="form-label small mb-1" for="flt-{{ $filter['name'] }}">{{ __($filter['label']) }}</label>
                        <select class="form-select" id="flt-{{ $filter['name'] }}" name="{{ $filter['name'] }}">
                            <option value="">{{ __('All') }}</option>
                            @foreach ($filter['options'] as $value => $text)
                                <option value="{{ $value }}" @selected((string) request()->query($filter['name']) === (string) $value)>{{ $text }}</option>
                            @endforeach
                        </select>
                    </div>
                @endforeach
                <div class="col-6 col-md-auto">
                    <label class="form-label small mb-1" for="flt-min">{{ __('Amount from') }}</label>
                    <input type="text" inputmode="decimal" dir="ltr" class="form-control" id="flt-min" name="min" value="{{ request()->query('min') }}" autocomplete="off">
                </div>
                <div class="col-6 col-md-auto">
                    <label class="form-label small mb-1" for="flt-max">{{ __('Amount to') }}</label>
                    <input type="text" inputmode="decimal" dir="ltr" class="form-control" id="flt-max" name="max" value="{{ request()->query('max') }}" autocomplete="off">
                </div>
                <div class="col-12 col-md">
                    <label class="form-label small mb-1" for="flt-q">{{ __('Search') }}</label>
                    <input type="search" class="form-control" id="flt-q" name="q" value="{{ request()->query('q') }}" placeholder="{{ __('Number or details') }}">
                </div>
                <div class="col-auto d-flex gap-2">
                    <button class="btn btn-primary" type="submit"><x-icon name="magnifying-glass" class="me-1" /> {{ __('Show') }}</button>
                    <a class="btn btn-light-secondary" href="{{ route($route.'.index') }}">{{ __('Clear') }}</a>
                </div>
            </form>
        @endif

        @if ($rows->isEmpty())
            <div class="empty-state">
                <div class="es-icon"><x-icon :name="$icon" /></div>
                <h2 class="h3">{{ __('Nothing here yet') }}</h2>
                @if ($canManage)
                    <p>{{ __('Press "Add :name" to create the first one.', ['name' => $singular]) }}</p>
                @endif
            </div>
        @else
            <table class="table table-hover w-100">
                <thead>
                    <tr>
                        @foreach (array_keys($columns) as $label)
                            <th>{{ __($label) }}</th>
                        @endforeach
                        <th class="text-end no-print">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($rows as $row)
                        <tr class="{{ $row->isCancelled() ? 'text-decoration-line-through text-muted' : '' }}">
                            @foreach ($columns as $column)
                                <td>{{ $column($row) }}</td>
                            @endforeach
                            <td class="text-end text-nowrap no-print">
                                <div class="act-group">
                                    @if ($row->isCancelled())<span class="badge bg-secondary-subtle text-secondary">{{ __('Cancelled') }}</span>@endif
                                    @if (! empty($attach))<a class="btn btn-sm btn-light-secondary" href="{{ route('attachments.index', ['type' => 'voucher', 'id' => $row->getKey()]) }}" title="{{ __('Files') }}" aria-label="{{ __('Files') }}"><x-icon name="paperclip" /></a>@endif
                                    <x-act kind="receipt" :href="route($route.'.receipt', ['id' => $row->getKey()])" blank />
                                    @if ($canManage && ! $row->isCancelled())
                                        <form method="POST" action="{{ route($route.'.destroy', ['id' => $row->getKey()]) }}" class="d-inline" data-confirm="{{ __('Cancel this? It stays on the list, crossed out.') }}" data-confirm-ok="{{ __('Cancel it') }}">
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
            {{ $rows->links('partials.pagination') }}
        @endif
    </div>
</div>
@endsection
