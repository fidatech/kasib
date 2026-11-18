@extends('layouts.app', ['title' => __('Backup & Restore')])

@section('content')
<x-page-header :title="__('Backup & Restore')" icon="database" :subtitle="__('A copy of everything the business has recorded. A copy is also made by itself every night.')">
    <form method="POST" action="{{ route('backups.store') }}" class="d-inline">
        @csrf
        <button type="submit" class="btn btn-primary"><x-icon name="plus" class="me-1" /> {{ __('Back up now') }}</button>
    </form>
</x-page-header>

<form method="POST" action="{{ route('backups.upload') }}" enctype="multipart/form-data" class="card mb-3">
    @csrf
    <div class="card-body">
        <div class="row g-2 align-items-end">
            <div class="col-12 col-md-7">
                <label class="form-label" for="b-file">{{ __('A backup file you downloaded before') }}</label>
                <input type="file" class="form-control @error('file') is-invalid @enderror" id="b-file" name="file" accept=".gz" required>
                @error('file')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
            </div>
            <div class="col-auto"><button type="submit" class="btn btn-outline-soft">{{ __('Add it to the list') }}</button></div>
        </div>
        <div class="form-text">{{ __('Only a file this system made for this business is taken, unchanged. It is then put back from the list below.') }}</div>
    </div>
</form>

<div class="card" data-no-export>
    <div class="card-body">
        @if ($backups->isEmpty())
            <div class="empty-state">
                <div class="es-icon"><x-icon name="database" /></div>
                <h2 class="h3">{{ __('Nothing here yet') }}</h2>
                <p>{{ __('Press "Back up now" to make the first copy.') }}</p>
            </div>
        @else
            <table class="table table-hover w-100 keep-table" data-rows>
                <thead><tr><th>{{ __('When') }}</th><th>{{ __('Kind') }}</th><th class="text-end">{{ __('Size') }}</th><th class="text-end">{{ __('Actions') }}</th></tr></thead>
                <tbody>
                    @foreach ($backups as $backup)
                        <tr>
                            <td class="text-nowrap">{{ fmt_date($backup->created_at) }} {{ $backup->created_at->timezone(config('app.timezone'))->format('H:i') }}</td>
                            <td>{{ $backup->kind === 'auto' ? __('By itself, at night') : __('By hand') }}</td>
                            <td class="text-end text-nowrap" dir="ltr">{{ human_size($backup->size) }}</td>
                            <td class="text-end">
                                <div class="d-flex flex-wrap gap-2 justify-content-end align-items-center">
                                    <a class="btn btn-sm btn-light-secondary" href="{{ route('backups.download', ['id' => $backup->id]) }}"><x-icon name="download" class="me-1" /> {{ __('Download') }}</a>
                                    <form method="POST" action="{{ route('backups.restore', ['id' => $backup->id]) }}" class="d-flex gap-2 align-items-center" data-confirm="{{ __('Put this backup back? Everything recorded after it is replaced. What you have now is saved first as a new backup.') }}" data-confirm-ok="{{ __('Put it back') }}">
                                        @csrf
                                        <input type="password" class="form-control form-control-sm" name="password" autocomplete="current-password" required placeholder="{{ __('Your password') }}" aria-label="{{ __('Your password') }}" style="max-width:170px">
                                        <button type="submit" class="btn btn-sm btn-outline-soft">{{ __('Restore') }}</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            @error('password')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
            {{ $backups->links('partials.pagination') }}
        @endif
    </div>
</div>
@endsection
