@extends(request()->boolean('print') ? 'layouts.print' : 'layouts.app', ['title' => __('Deals')])

@section('content')
<x-page-header :title="__('Deals')" icon="handshake" :subtitle="__('Something bought to be sold again: record what it cost and what came in, and the profit is worked out.')" />

@if ($canManage)
    <form method="POST" action="{{ route('deals.store') }}" class="card mb-3 no-print">
        @csrf
        <div class="card-body">
            <div class="row g-2 align-items-end">
                <div class="col-12 col-md-5">
                    <label class="form-label" for="d-name">{{ __('New deal') }} <span class="req">*</span></label>
                    <input type="text" class="form-control @error('name') is-invalid @enderror" id="d-name" name="name" value="{{ old('name') }}" maxlength="150" required placeholder="{{ __('For example: 200 sacks of flour from Mazar') }}">
                    @error('name')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                </div>
                <div class="col-12 col-md">
                    <label class="form-label" for="d-notes">{{ __('Notes') }}</label>
                    <input type="text" class="form-control" id="d-notes" name="notes" value="{{ old('notes') }}" maxlength="2000">
                </div>
                <div class="col-auto"><button type="submit" class="btn btn-primary"><x-icon name="plus" class="me-1" /> {{ __('Open the deal') }}</button></div>
            </div>
        </div>
    </form>
@endif

<div class="card">
    <div class="card-body">
        <form method="GET" class="row g-2 mb-3 no-print">
            <div class="col-12 col-md"><input type="search" class="form-control" name="q" value="{{ $term }}" placeholder="{{ __('Search…') }}" aria-label="{{ __('Search') }}"></div>
            <div class="col col-md-auto">
                <select class="form-select" name="status" aria-label="{{ __('Status') }}">
                    <option value="">{{ __('Status') }}</option>
                    <option value="open" @selected($status === 'open')>{{ __('Open') }}</option>
                    <option value="closed" @selected($status === 'closed')>{{ __('Closed') }}</option>
                </select>
            </div>
            <div class="col-auto"><button class="btn btn-light-secondary" type="submit">{{ __('Show') }}</button></div>
        </form>

        @if ($deals->isEmpty())
            <div class="empty-state">
                <div class="es-icon"><x-icon name="handshake" /></div>
                <h2 class="h3">{{ $term !== '' || $status ? __('Nothing found') : __('Nothing here yet') }}</h2>
            </div>
        @else
            <table class="table table-hover w-100">
                <thead><tr><th>{{ __('No.') }}</th><th>{{ __('Deal') }}</th><th>{{ __('Opened on') }}</th><th>{{ __('Status') }}</th><th class="text-end">{{ __('Cost') }} {{ $base }}</th><th class="text-end">{{ __('Received') }} {{ $base }}</th><th class="text-end">{{ __('Profit or loss') }} {{ $base }}</th></tr></thead>
                <tbody>
                    @foreach ($deals as $deal)
                        <tr>
                            <td class="text-nowrap"><a href="{{ route('deals.show', ['id' => $deal->id]) }}">{{ $deal->number }}</a></td>
                            <td><a href="{{ route('deals.show', ['id' => $deal->id]) }}">{{ $deal->name }}</a></td>
                            <td class="text-nowrap">{{ fmt_date($deal->opened_on) }}</td>
                            <td>@if ($deal->isOpen())<span class="badge bg-success-subtle text-success">{{ __('Open') }}</span>@else<span class="badge bg-secondary-subtle text-secondary">{{ __('Closed') }}</span>@endif</td>
                            <td class="text-end text-nowrap">{{ money($figures[$deal->id]['cost']) }}</td>
                            <td class="text-end text-nowrap">{{ money($figures[$deal->id]['received']) }}</td>
                            <td class="text-end text-nowrap">{{ money($deal->isOpen() ? $figures[$deal->id]['result'] : $deal->result) }}@if ($deal->isOpen()) <span class="small text-muted">{{ __('so far') }}</span>@endif</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            {{ $deals->links('partials.pagination') }}
        @endif
    </div>
</div>
@endsection
