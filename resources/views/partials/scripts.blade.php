@php
    $erpText = [
        'cancel' => __('app.actions.cancel'),
        'confirmOk' => __('app.actions.yes'),
        'confirmTitle' => __('app.actions.confirm_title'),
        'noResults' => __('app.nav.no_results'),
        'addNew' => __('New'),
        'failed' => __('Something went wrong. Please try again.'),
    ];
@endphp
<script>window.ERP_TEXT = {{ Illuminate\Support\Js::from($erpText) }};</script>
<script src="{{ asset_v('js/app.js') }}" defer></script>
@stack('scripts')
{{-- Pages are fetched in the background as soon as the pointer rests on their link, so opening them feels instant. Exports, printed pages and links that mark something as read are left alone. --}}
@verbatim
<script type="speculationrules">{"prefetch":[{"where":{"and":[{"href_matches":"/*"},{"not":{"href_matches":"/messages/*"}},{"not":{"href_matches":"/*\\?*export=*"}},{"not":{"href_matches":"/*\\?*print=*"}},{"not":{"selector_matches":"[target=_blank]"}}]},"eagerness":"moderate"}]}</script>
@endverbatim
