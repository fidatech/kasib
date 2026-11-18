@extends('layouts.app', ['title' => __('Assistant')])

@section('content')
<x-page-header :title="__('Assistant')" icon="comments" :subtitle="__('Ask about your business in your own words: who owes you, how much cash there is, how this month compares with the last.')" />

@if (! $ready)
    <div class="card"><div class="card-body"><div class="empty-state"><div class="es-icon"><x-icon name="comments" /></div><h2 class="h3">{{ __('The assistant is not switched on yet.') }}</h2><p class="text-muted">{{ __('Ask the support of the system to switch it on.') }}</p></div></div></div>
@else
    <form method="POST" action="{{ route('assistant.store') }}" class="card mb-3">
        @csrf
        <div class="card-body">
            <label class="form-label" for="a-question">{{ __('Your question') }}</label>
            <textarea class="form-control @error('question') is-invalid @enderror" id="a-question" name="question" rows="2" maxlength="1000" required autofocus placeholder="{{ __('For example: who owes me the most?') }}">{{ old('question') }}</textarea>
            @error('question')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
            <div class="form-text">{{ __('A summary of your figures goes with the question to the assistant service (Claude, by Anthropic) and the answer comes back here. It reads; it records nothing. Check an important number in its report.') }}</div>
        </div>
        <div class="card-footer d-flex justify-content-between align-items-center">
            <span class="text-muted small">{{ __(':count questions left today', ['count' => $left]) }}</span>
            <button type="submit" class="btn btn-primary" @disabled($left < 1)><x-icon name="paper-plane" class="me-1" /> {{ __('Ask') }}</button>
        </div>
    </form>

    @foreach ($questions as $row)
        <div class="card mb-2">
            <div class="card-body">
                <div class="fw-semibold mb-2" dir="auto">{{ $row->question }}</div>
                <div dir="auto" style="white-space:pre-line">{{ $row->answer }}</div>
                <div class="text-muted small mt-2">{{ $row->created_at?->diffForHumans() }}</div>
            </div>
        </div>
    @endforeach
    @if ($questions->isNotEmpty())
        <form method="POST" action="{{ route('assistant.destroy') }}" class="text-end" data-confirm="{{ __('Clear your questions?') }}" data-confirm-ok="{{ __('Clear') }}">@csrf @method('DELETE')<button type="submit" class="btn btn-sm btn-outline-secondary">{{ __('Clear my questions') }}</button></form>
    @endif
@endif
@endsection
