{{-- The demo of the website: its address, user and password, always in sight. --}}
@php($demo = \App\Services\Demo::info())
@if ($demo)
<div class="site-demo {{ $class ?? '' }}" id="demo">
    <div class="site-demo-text">
        <strong>{{ __('Try the demo') }}</strong>
        <span>{{ __('A business with example papers, open to everybody. Nothing to register; it is emptied every night.') }}</span>
    </div>
    <dl class="site-demo-facts">
        <div><dt>{{ __('Address') }}</dt><dd dir="ltr"><a href="{{ $demo['url'] }}" target="_blank" rel="noopener">{{ $demo['host'] }}</a></dd></div>
        <div><dt>{{ __('User') }}</dt><dd dir="ltr" class="user-select-all">{{ $demo['email'] }}</dd></div>
        <div><dt>{{ __('Password') }}</dt><dd dir="ltr" class="user-select-all">{{ $demo['password'] }}</dd></div>
    </dl>
    <a class="btn btn-primary" href="{{ $demo['url'] }}" target="_blank" rel="noopener">{{ __('Open the demo') }}</a>
</div>
@endif
