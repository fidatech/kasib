@php
    $erpText = [
        'cancel' => __('app.actions.cancel'),
        'confirmOk' => __('app.actions.yes'),
        'confirmTitle' => __('app.actions.confirm_title'),
        'noResults' => __('app.nav.no_results'),
        'addNew' => __('New'),
        'failed' => __('Something went wrong. Please try again.'),
        'tourNext' => __('Next'), 'tourBack' => __('Back'), 'tourSkip' => __('Skip'), 'tourDone' => __('Done'),
    ];
@endphp
<script>window.ERP_TEXT = {{ Illuminate\Support\Js::from($erpText) }};@if (tenant() && auth('web')->check() && \Illuminate\Support\Facades\Route::has('help.index')) window.ERP_TOUR = {{ Illuminate\Support\Js::from(['user' => auth('web')->id(), 'auto' => request()->routeIs('dashboard'), 'steps' => \App\Http\Controllers\HelpController::tour()]) }};@endif</script>
<script src="{{ asset_v('js/app.js') }}" defer></script>
@stack('scripts')
