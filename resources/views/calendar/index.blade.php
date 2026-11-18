@extends('layouts.app', ['title' => __('Calendar')])

@section('content')
<x-page-header :title="__('Calendar')" icon="calendar-days" :subtitle="$title">
    <a class="btn btn-outline-soft" href="{{ route('calendar.index', ['month' => $offset - 1]) }}">{{ __('Month before') }}</a>
    @if ($offset !== 0)<a class="btn btn-outline-soft" href="{{ route('calendar.index') }}">{{ __('This month') }}</a>@endif
    <a class="btn btn-outline-soft" href="{{ route('calendar.index', ['month' => $offset + 1]) }}">{{ __('Month after') }}</a>
</x-page-header>

<div class="card">
    <div class="card-body">
        <div class="d-flex flex-wrap gap-3 small mb-3">
            @foreach ($kinds as $kind => $label)
                <span><span class="badge {{ ['due' => 'bg-danger-subtle text-danger', 'reminder' => 'bg-primary-subtle text-primary', 'expense' => 'bg-warning-subtle text-warning', 'document' => 'bg-secondary-subtle text-secondary'][$kind] }}">&nbsp;</span> {{ __($label) }}</span>
            @endforeach
        </div>
        <div class="table-responsive">
            <table class="table table-bordered w-100 keep-table" style="table-layout: fixed; min-width: 44rem">
                <thead><tr>@foreach ($weekdays as $weekday)<th class="text-center small">{{ __($weekday) }}</th>@endforeach</tr></thead>
                <tbody>
                    @foreach ($weeks as $week)
                        <tr>
                            @foreach (array_pad($week, 7, null) as $day)
                                <td class="align-top p-1 {{ $day && $day['today'] ? 'table-active' : '' }}" style="height: 6.5rem">
                                    @if ($day)
                                        <div class="small fw-semibold {{ $day['today'] ? 'text-primary' : 'text-muted' }}">{{ $day['number'] }}</div>
                                        @foreach (array_slice($day['events'], 0, 4) as $event)
                                            <a class="d-block text-truncate text-decoration-none badge text-start fw-normal mb-1 {{ ['due' => 'bg-danger-subtle text-danger', 'reminder' => 'bg-primary-subtle text-primary', 'expense' => 'bg-warning-subtle text-warning', 'document' => 'bg-secondary-subtle text-secondary'][$event['kind']] }}" @if ($event['url']) href="{{ $event['url'] }}" @endif title="{{ __($kinds[$event['kind']]) }}: {{ $event['text'] }}">{{ $event['text'] }}</a>
                                        @endforeach
                                        @if (count($day['events']) > 4)<div class="small text-muted">+{{ count($day['events']) - 4 }}</div>@endif
                                    @endif
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
