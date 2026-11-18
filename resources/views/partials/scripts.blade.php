@php
    $erpText = [
        'cancel' => __('app.actions.cancel'),
        'confirmOk' => __('app.actions.yes'),
        'confirmTitle' => __('app.actions.confirm_title'),
        'noResults' => __('app.nav.no_results'),
        'addNew' => __('New'),
        'failed' => __('Something went wrong. Please try again.'),
        'moreFilters' => __('More filters'), 'fewerFilters' => __('Fewer filters'), 'clearFilters' => __('Clear all'), 'removeFilter' => __('Remove'),
        // heads of table columns, by the colour their amounts are shown in
        'tones' => [
            'in' => array_map('__', ['Money In', 'In', 'Received', 'Income', 'They owe us', 'Paid in', 'Receipts', 'Came in', 'Sales']),
            'out' => array_map('__', ['Money Out', 'Out', 'Paid out', 'Expenses', 'We owe them', 'Payments', 'Went out', 'Discount', 'Fee', 'Purchases']),
            'sign' => array_map('__', ['Balance', 'Profit', 'Change', 'Difference', 'Profit or loss', 'Left', 'Net']),
            'debit' => array_map('__', ['Debit']),
            'credit' => array_map('__', ['Credit']),
        ],
        'tourNext' => __('Next'), 'tourBack' => __('Back'), 'tourSkip' => __('Skip'), 'tourDone' => __('Done'),
    ];
@endphp
<script>window.ERP_TEXT = {{ Illuminate\Support\Js::from($erpText) }};@if (tenant() && auth('web')->check() && \Illuminate\Support\Facades\Route::has('help.index')) window.ERP_TOUR = {{ Illuminate\Support\Js::from(['user' => auth('web')->id(), 'auto' => request()->routeIs('dashboard') || \App\Services\Demo::is(tenant()), 'steps' => \App\Http\Controllers\HelpController::tour()]) }};@endif</script>
<script src="{{ asset_v('js/app.js') }}" defer></script>
@stack('scripts')
