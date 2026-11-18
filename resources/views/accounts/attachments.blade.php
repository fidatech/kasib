@extends('layouts.app', ['title' => __('Files')])

@section('content')
<x-page-header :title="$title" icon="paperclip" :crumbs="[[__('Back'), $back], [__('Files')]]" :subtitle="__('Photos and PDFs kept with this record: a receipt, a contract, an identity card.')" />

@if ($canManage)
    <form method="POST" action="{{ route('attachments.store', ['type' => $type, 'id' => $subject->getKey()]) }}" enctype="multipart/form-data" class="card mb-3">
        @csrf
        <div class="card-body">
            <div class="row g-2 align-items-end">
                <div class="col-12 col-md-7">
                    <label class="form-label" for="a-file">{{ __('Photo or PDF, up to 5 MB') }} <span class="req">*</span></label>
                    <input type="file" class="form-control @error('file') is-invalid @enderror" id="a-file" name="file" accept="image/png,image/jpeg,image/webp,application/pdf" required>
                    @error('file')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                </div>
                <div class="col-auto"><button type="submit" class="btn btn-primary"><x-icon name="paperclip" class="me-1" /> {{ __('Attach') }}</button></div>
            </div>
        </div>
    </form>
@endif

<div class="card">
    <div class="card-body">
        @if ($files->isEmpty())
            <div class="empty-state">
                <div class="es-icon"><x-icon name="paperclip" /></div>
                <h2 class="h3">{{ __('Nothing here yet') }}</h2>
            </div>
        @else
            <table class="table table-hover w-100" data-rows>
                <thead><tr><th>{{ __('File') }}</th><th class="text-end">{{ __('Size') }}</th><th>{{ __('User') }}</th><th>{{ __('When') }}</th><th class="text-end">{{ __('Actions') }}</th></tr></thead>
                <tbody>
                    @foreach ($files as $file)
                        <tr>
                            <td><a href="{{ route('attachments.show', ['type' => $type, 'id' => $subject->getKey(), 'file' => $file->id]) }}" target="_blank" rel="noopener">{{ $file->name }}</a></td>
                            <td class="text-end text-nowrap" dir="ltr">{{ human_size($file->size) }}</td>
                            <td>{{ $file->creator?->name }}</td>
                            <td class="text-nowrap">{{ fmt_date($file->created_at) }}</td>
                            <td class="text-end">
                                @if ($canManage)
                                    <form method="POST" action="{{ route('attachments.destroy', ['type' => $type, 'id' => $subject->getKey(), 'file' => $file->id]) }}" class="d-inline" data-confirm="{{ __('Delete this?') }}" data-confirm-ok="{{ __('Delete') }}">
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
        @endif
    </div>
</div>
@endsection
