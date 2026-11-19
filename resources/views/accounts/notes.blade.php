@extends('layouts.app', ['title' => __('Notes and promises')])

@section('content')
<div data-modal-stay hidden></div>
<x-page-header :title="$party->name" :icon="$group->icon" :crumbs="[[$group->label(), route('accounts.index', ['group' => $group->key])], [__('Notes and promises')]]" :subtitle="__('Notes and promises').($party->phone ? ' · '.$party->phone : '')">
    <a class="btn btn-outline-soft" href="{{ route('accounts.statement', ['group' => $group->key, 'id' => $party->id]) }}">{{ __('Statement') }}</a>
    <a class="btn btn-outline-soft" href="{{ route('attachments.index', ['type' => 'party', 'id' => $party->id]) }}" data-modal><x-icon name="paperclip" class="me-1" /> {{ __('Files') }}</a>
</x-page-header>

@if ($canManage)
    <form method="POST" action="{{ route('accounts.notes.store', ['group' => $group->key, 'id' => $party->id]) }}" class="card mb-3">
        @csrf
        <div class="card-body">
            <div class="row g-2 align-items-end">
                <div class="col-12 col-md">
                    <label class="form-label" for="n-note">{{ __('Note') }} <span class="req">*</span></label>
                    <input type="text" class="form-control @error('note') is-invalid @enderror" id="n-note" name="note" value="{{ old('note') }}" maxlength="500" required placeholder="{{ __('For example: said he will pay on Thursday') }}">
                    @error('note')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                </div>
                <div class="col-6 col-md-3">
                    <label class="form-label">{{ __('Remind me on') }}</label>
                    <x-date name="remind_on" :value="old('remind_on')" />
                </div>
                <div class="col-auto"><button type="submit" class="btn btn-primary"><x-icon name="plus" class="me-1" /> {{ __('Add') }}</button></div>
            </div>
        </div>
    </form>
@endif

<div class="card">
    <div class="card-body">
        @include('accounts.notes-table', ['notes' => $notes, 'withParty' => false])
    </div>
</div>
@endsection
