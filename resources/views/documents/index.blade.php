@extends('layouts.app', ['title' => __('Document Vault')])

@section('content')
<x-page-header :title="__('Document Vault')" icon="vault" :subtitle="__('The papers of the business itself: licences, contracts, identity cards. The owner is told thirty days before one expires.')" />

@if ($canManage)
    <form method="POST" action="{{ route('documents.store') }}" enctype="multipart/form-data" class="card mb-3">
        @csrf
        <div class="card-body">
            <div class="row g-3">
                <div class="col-12 col-md-4">
                    <label class="form-label" for="d-title">{{ __('Title') }} <span class="req">*</span></label>
                    <input type="text" class="form-control @error('title') is-invalid @enderror" id="d-title" name="title" value="{{ old('title') }}" maxlength="200" required>
                    @error('title')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label" for="d-category">{{ __('Kind') }}</label>
                    <select class="form-select" id="d-category" name="category">
                        @foreach ($categories as $key => $label)
                            <option value="{{ $key }}" @selected(old('category', 'other') === $key)>{{ __($label) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label">{{ __('Expires on') }}</label>
                    <x-date name="expires_on" :value="old('expires_on')" />
                    @error('expires_on')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                </div>
                <div class="col-12 col-md-4">
                    <label class="form-label" for="d-file">{{ __('File, up to 10 MB') }} <span class="req">*</span></label>
                    <input type="file" class="form-control @error('file') is-invalid @enderror" id="d-file" name="file" accept="image/png,image/jpeg,image/webp,application/pdf,.doc,.docx,.xls,.xlsx" required>
                    @error('file')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                </div>
                <div class="col-12 col-md-10">
                    <label class="form-label" for="d-notes">{{ __('Note') }}</label>
                    <input type="text" class="form-control" id="d-notes" name="notes" value="{{ old('notes') }}" maxlength="500">
                </div>
                <div class="col-12 col-md-2 d-flex align-items-end"><button type="submit" class="btn btn-primary w-100"><x-icon name="plus" class="me-1" /> {{ __('Add') }}</button></div>
            </div>
        </div>
    </form>
@endif

<div class="card">
    <div class="card-body">
        <form method="GET" class="filter-bar row g-2 mb-3 no-print">
            <div class="col-12 col-md"><input type="search" class="form-control" name="q" value="{{ $term }}" placeholder="{{ __('Search…') }}" aria-label="{{ __('Search') }}"></div>
            <div class="col col-md-auto">
                <select class="form-select" name="category" aria-label="{{ __('Kind') }}" onchange="this.form.submit()">
                    <option value="">{{ __('Kind') }}</option>
                    @foreach ($categories as $key => $label)
                        <option value="{{ $key }}" @selected(request('category') === $key)>{{ __($label) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-auto"><button class="btn btn-light-secondary" type="submit" aria-label="{{ __('Search') }}"><x-icon name="magnifying-glass" /></button></div>
        </form>

        @if ($documents->isEmpty())
            <div class="empty-state"><div class="es-icon"><x-icon name="vault" /></div><h2 class="h3">{{ __('Nothing here yet') }}</h2></div>
        @else
            <table class="table table-hover w-100">
                <thead><tr><th>{{ __('Title') }}</th><th>{{ __('Kind') }}</th><th>{{ __('Expires on') }}</th><th>{{ __('Note') }}</th><th class="text-end">{{ __('Size') }}</th><th>{{ __('User') }}</th><th class="text-end no-print">{{ __('Actions') }}</th></tr></thead>
                <tbody>
                    @foreach ($documents as $document)
                        @php $left = $document->expires_on ? (int) round((strtotime($document->expires_on->toDateString()) - strtotime(today_iso())) / 86400) : null; @endphp
                        <tr>
                            <td><a href="{{ route('documents.show', ['id' => $document->id]) }}" target="_blank" rel="noopener" data-no-soft>{{ $document->title }}</a></td>
                            <td>{{ __($categories[$document->category] ?? $document->category) }}</td>
                            <td class="text-nowrap">
                                {{ fmt_date($document->expires_on) }}
                                @if ($left !== null && $left < 0)<span class="badge bg-danger-subtle text-danger">{{ __('Expired') }}</span>
                                @elseif ($left !== null && $left <= 30)<span class="badge bg-warning-subtle text-warning">{{ __('In :days days', ['days' => $left]) }}</span>@endif
                            </td>
                            <td>{{ $document->notes }}</td>
                            <td class="text-end text-nowrap" dir="ltr">{{ human_size($document->size) }}</td>
                            <td>{{ $document->creator?->name }}</td>
                            <td class="text-end no-print">
                                @if ($canManage)
                                    <form method="POST" action="{{ route('documents.destroy', ['id' => $document->id]) }}" class="d-inline" data-confirm="{{ __('Delete this?') }}" data-confirm-ok="{{ __('Delete') }}">
                                        @csrf
                                        @method('DELETE')
                                        <x-act kind="delete" />
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            {{ $documents->links() }}
        @endif
    </div>
</div>
@endsection
