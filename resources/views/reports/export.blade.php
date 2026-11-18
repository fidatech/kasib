@extends('layouts.app', ['title' => __('Export Center')])

@section('content')
<x-page-header :title="__('Export Center')" icon="file-excel" :crumbs="[[__('Reports'), route('reports.index')], [__('Export Center')]]" :subtitle="__('One Excel workbook of a period, with a summary and a sheet for every module you tick.')" />

<form method="GET" action="{{ route('export.download') }}" class="card" data-no-soft>
    <div class="card-body">
        <div class="row g-3 mb-3">
            <div class="col-6 col-md-3">
                <label class="form-label">{{ __('From') }} <span class="req">*</span></label>
                <x-date name="from" :value="old('from', $from)" :required="true" />
                @error('from')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
            </div>
            <div class="col-6 col-md-3">
                <label class="form-label">{{ __('To') }} <span class="req">*</span></label>
                <x-date name="to" :value="old('to', $to)" :required="true" />
                @error('to')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
            </div>
        </div>
        <label class="form-label">{{ __('Sheets of the workbook') }}</label>
        <div class="row g-2">
            @foreach ($modules as $key => $name)
                <div class="col-12 col-sm-6 col-lg-4">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="x-{{ $key }}" name="modules[]" value="{{ $key }}" @checked(in_array($key, (array) old('modules', array_keys($modules)), true))>
                        <label class="form-check-label" for="x-{{ $key }}">{{ __($name) }}</label>
                    </div>
                </div>
            @endforeach
        </div>
        @error('modules')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
        <div class="form-text mt-3">{{ __('The balance sheet and the trial balance are as on the last day of the period. Amounts are numbers, so Excel can add and filter them.') }}</div>
    </div>
    <div class="card-footer d-flex gap-2 justify-content-end">
        <button type="submit" class="btn btn-primary"><x-icon name="download" class="me-1" /> {{ __('Download the workbook') }}</button>
    </div>
</form>
@endsection
