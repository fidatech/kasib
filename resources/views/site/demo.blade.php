{{-- The demos of the website, Starter and Pro: their addresses, user and password, always in sight. --}}
@php($demos = \App\Services\Demo::info())
@if ($demos)
<div class="site-demo {{ $class ?? '' }}" id="demo">
    <div class="site-demo-text">
        <strong>{{ count($demos) > 1 ? __('Try the demos') : __('Try the demo') }}</strong>
        <span>{{ __('A business with example papers, open to everybody. Nothing to register; it starts fresh every midnight.') }}</span>
        @if (count($demos) > 1)<span>{{ __('Compare the two plans: the same business on Starter and on Pro.') }}</span>@endif
    </div>
    <div class="site-demo-list">
        @foreach (['starter', 'pro'] as $plan)
            @continue(! isset($demos[$plan]))
            @php($demo = $demos[$plan])
            <div class="site-demo-plan">
                <span class="site-demo-badge is-{{ $plan }}">{{ $plan === 'pro' ? __('Pro') : __('Starter') }}</span>
                <dl class="site-demo-facts">
                    <div><dt>{{ __('Address') }}</dt><dd dir="ltr"><a href="{{ $demo['url'] }}" target="_blank" rel="noopener">{{ $demo['host'] }}</a></dd></div>
                    <div><dt>{{ __('User') }}</dt><dd dir="ltr" class="user-select-all">{{ $demo['email'] }}</dd></div>
                    <div><dt>{{ __('Password') }}</dt><dd dir="ltr" class="user-select-all">{{ $demo['password'] }}</dd></div>
                </dl>
                <a class="btn {{ $plan === 'pro' ? 'btn-primary' : 'btn-outline-primary' }}" href="{{ $demo['url'] }}" target="_blank" rel="noopener">{{ $plan === 'pro' ? __('Open the Pro demo') : __('Open the Starter demo') }}</a>
            </div>
        @endforeach
    </div>
</div>
@endif
