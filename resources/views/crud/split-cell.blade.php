{{-- One cell of a line of its own (crud/index: $splitColumns): a text, a colour, a small tag beside it, a link. --}}
@if ($cell === null)
    <td class="amt-zero" data-label="{{ $label }}" data-keep-label>—</td>
@else
    <td class="{{ $cell['class'] ?? '' }}" data-label="{{ $label }}" data-keep-label>
        @if (! empty($cell['url']))<a href="{{ $cell['url'] }}" class="cur-link">{{ $cell['text'] }}</a>@else<span dir="ltr">{{ $cell['text'] }}</span>@endif
        @if (! empty($cell['tag']))<span class="bal-tag is-{{ $cell['tone'] ?? 'zero' }}">{{ $cell['tag'] }}</span>@endif
    </td>
@endif
