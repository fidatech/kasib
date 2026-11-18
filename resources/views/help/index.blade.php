@extends('layouts.app', ['title' => __('Help')])

@section('content')
<x-page-header :title="__('Help')" icon="circle-question" :subtitle="__('How the daily work is done, step by step.')">
    <button type="button" class="btn btn-primary" data-tour-start><x-icon name="circle-info" class="me-1" /> {{ __('Show me around the screen') }}</button>
    <a class="btn btn-outline-soft" href="{{ route('service.tickets') }}"><x-icon name="headset" class="me-1" /> {{ __('Support') }}</a>
</x-page-header>

@if ($videos)
    <div class="card mb-3">
        <div class="card-header"><h2 class="h5 card-title mb-0">{{ __('Videos') }}</h2></div>
        <div class="card-body">
            <div class="row g-2">
                @foreach ($videos as $video)
                    <div class="col-12 col-md-6 col-xl-4"><a class="d-flex align-items-center gap-2 border rounded p-3 h-100 text-decoration-none" href="{{ $video['url'] }}" target="_blank" rel="noopener noreferrer"><span class="icon-soft primary"><x-icon name="circle-info" /></span><span class="fw-semibold">{{ $video['title'] }}</span></a></div>
                @endforeach
            </div>
        </div>
    </div>
@endif

<div class="row g-3">
    @foreach ($topics as $title => $steps)
        <div class="col-12 col-lg-6">
            <div class="card h-100 mb-0">
                <div class="card-header"><h2 class="h5 card-title mb-0">{{ __($title) }}</h2></div>
                <div class="card-body"><ol class="mb-0 ps-3">@foreach ($steps as $step)<li class="mb-1">{{ __($step) }}</li>@endforeach</ol></div>
            </div>
        </div>
    @endforeach
</div>
@endsection
