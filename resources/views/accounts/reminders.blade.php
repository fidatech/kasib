@extends('layouts.app', ['title' => __('Reminders')])

@section('content')
<x-page-header :title="__('Reminders')" icon="bell" :subtitle="__('The notes of every account that are not done yet; the ones whose day has come are first.')" />

<div class="card">
    <div class="card-body">
        @include('accounts.notes-table', ['notes' => $notes, 'withParty' => true])
        {{ $notes->links('partials.pagination') }}
    </div>
</div>
@endsection
