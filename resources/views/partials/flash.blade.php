{{-- Messages set with ->with('success', '...') and friends are shown as toasts. --}}
@foreach (['success', 'warning', 'danger', 'info'] as $type)
    @if (session($type))
        <div data-toast="{{ $type }}" hidden>{{ session($type) }}</div>
    @endif
@endforeach
{{-- the file of a customer lacks something for the transaction just tried: its pop-up opens by itself --}}
@if (is_array(session('complete_customer')))
    <button type="button" hidden data-quick-auto data-quick-add="{{ session('complete_customer')['url'] }}" data-quick-title="{{ session('complete_customer')['title'] }}" data-quick-target="select[data-customer]"></button>
@endif
