{{-- The daily work of a business, one row of buttons under the navbar on every page; each opens its page (config/quickbar.php). --}}
<nav class="quickbar no-print" aria-label="{{ __('Daily work') }}">
    <ul class="quickbar-list">
        @foreach (\App\Support\Menu::bar(auth()->user()) as $entry)
            @php $here = $entry['current'] || $entry['open']; @endphp
            <li><a class="quickbar-item {{ $here ? 'active' : '' }}" href="{{ \App\Support\Menu::url($entry) }}" @if ($here) aria-current="page" @endif><span class="icon-soft {{ $entry['tone'] }}"><x-icon :name="$entry['icon']" /></span><span>{{ __($entry['label']) }}</span></a></li>
        @endforeach
        <li class="quickbar-end"><button type="button" class="quickbar-item" data-bs-toggle="modal" data-bs-target="#ratesModal"><span class="icon-soft warning"><x-icon name="coins" /></span><span>{{ __('Exchange Rates') }}</span></button></li>
    </ul>
</nav>
