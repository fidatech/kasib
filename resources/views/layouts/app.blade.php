{{-- ?print=1 turns any screen into a clean paper with the company logo (see layouts.print) --}}
@if (request()->boolean('print'))
@include('layouts.print')
@else
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ text_dir() }}" data-bs-theme="light" data-sidebar="light" data-layout="vertical" data-primary="blue" data-width="fluid" data-icons="{{ asset_v('icons.svg') }}" data-home="{{ route('dashboard') }}">
<head>
    @include('partials.head')
</head>
<body data-soft>
<div class="app-wrapper">
    @include('partials.sidebar')
    <div class="app-main">
        @include('partials.topbar')
        @include('partials.quickbar')
        <main class="app-content">
            <div class="content-container">
                @if (\App\Services\Demo::is(tenant()))<div class="alert alert-info py-2 mb-3" role="note" data-page-only>{{ \App\Services\Demo::planOf(tenant()) === 'starter' ? __('This is the Starter demo. Everything recorded here is erased at midnight (12:00 AM).') : __('This is the Pro demo. Everything recorded here is erased at midnight (12:00 AM).') }}</div>@endif
                <div data-page-only>@include('partials.service-notices')</div>
                @yield('content')
            </div>
        </main>
        <footer class="app-footer">
            <span>© {{ date('Y') }} {{ config('app.name') }}</span>
            @unless (white_label())<nav><span class="text-muted">{{ __('app.by') }} <a href="https://fida.af" target="_blank" rel="noopener">Fida Technologies</a></span></nav>@endunless
        </footer>
    </div>
</div>

<nav class="app-tabbar" aria-label="{{ __('app.nav.menu') }}">
    <a href="{{ route('dashboard') }}" class="tab-item {{ request()->routeIs('dashboard') ? 'active' : '' }}"><x-icon name="house" /><em>{{ __('app.nav.home') }}</em></a>
    <button type="button" class="tab-item" data-bs-toggle="modal" data-bs-target="#searchModal"><x-icon name="magnifying-glass" /><em>{{ __('app.nav.search') }}</em></button>
    <button type="button" class="tab-item" id="tabMenu"><x-icon name="grip" /><em>{{ __('app.nav.menu') }}</em></button>
</nav>

<div class="modal fade" id="searchModal" tabindex="-1" aria-label="{{ __('app.nav.search') }}" aria-hidden="true">
    <div class="modal-dialog modal-dialog-scrollable search-dialog">
        <div class="modal-content">
            <div class="search-input"><x-icon name="magnifying-glass" /><input type="text" id="searchInput" placeholder="{{ __('app.nav.search_placeholder') }}" autocomplete="off"><kbd>Esc</kbd></div>
            <div class="modal-body p-2" id="searchResults"></div>
        </div>
    </div>
</div>

@include('partials.rates-modal')

<form id="logoutForm" method="POST" action="{{ route('logout') }}" hidden
      data-confirm="{{ __('app.nav.sign_out_question') }}" data-confirm-text="{{ __('app.nav.sign_out_text') }}"
      data-confirm-ok="{{ __('app.nav.sign_out') }}" data-confirm-style="primary">@csrf</form>

<form id="refreshForm" method="POST" action="{{ route('system.refresh') }}" hidden>@csrf</form>
<form id="noticesReadForm" method="POST" action="{{ route('notices.read') }}" hidden>@csrf</form>
<form id="localeForm" method="POST" action="{{ route('preferences.locale') }}" hidden>@csrf</form>

@include('partials.flash')
@include('partials.scripts')
</body>
</html>
@endif
