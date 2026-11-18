@extends('layouts.app', ['title' => __('Reports')])

@section('content')
<x-page-header :title="__('Reports')" icon="chart-column" :subtitle="__('Every report can be downloaded as Excel, and printed or saved as PDF.')" />

<div class="card">
    <div class="card-body">
        <div class="row g-2">
            @foreach ($reports as $name => [$title, $icon, $about])
                <div class="col-12 col-md-6 col-xl-4">
                    <a class="d-flex align-items-start gap-3 border rounded p-3 h-100 text-decoration-none" href="{{ route($name) }}">
                        <span class="icon-soft primary"><x-icon :name="$icon" /></span>
                        <span><span class="d-block fw-semibold">{{ __($title) }}</span><span class="d-block small text-muted">{{ __($about) }}</span></span>
                    </a>
                </div>
            @endforeach
        </div>
    </div>
</div>
@endsection
