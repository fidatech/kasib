{{-- The rows of notes: on the page of one account, and on the page of every reminder. --}}
@if ($notes->isEmpty())
    <div class="empty-state">
        <div class="es-icon"><x-icon name="bell" /></div>
        <h2 class="h3">{{ __('Nothing here yet') }}</h2>
    </div>
@else
    <table class="table table-hover w-100" data-rows>
        <thead><tr>@if ($withParty)<th>{{ __('Account') }}</th>@endif<th>{{ __('Note') }}</th><th>{{ __('Remind me on') }}</th><th>{{ __('User') }}</th><th class="text-end">{{ __('Actions') }}</th></tr></thead>
        <tbody>
            @foreach ($notes as $note)
                @php $late = ! $note->done_at && $note->remind_on && $note->remind_on->toDateString() <= today_iso(); @endphp
                <tr class="{{ $note->done_at ? 'text-decoration-line-through text-muted' : '' }}">
                    @if ($withParty)<td><a href="{{ route('accounts.notes', ['group' => $note->party->group->key, 'id' => $note->party_id]) }}">{{ $note->party->name }}</a><div class="small text-muted">{{ $note->party->phone }}</div></td>@endif
                    <td>{{ $note->note }}</td>
                    <td class="text-nowrap">{{ fmt_date($note->remind_on) }} @if ($late)<span class="badge bg-danger-subtle text-danger">{{ __('Due') }}</span>@endif</td>
                    <td>{{ $note->creator?->name }}<div class="small text-muted">{{ fmt_date($note->created_at) }}</div></td>
                    <td class="text-end text-nowrap">
                        @if ($canManage)
                            <form method="POST" action="{{ route('notes.toggle', ['note' => $note->id]) }}" class="d-inline">
                                @csrf
                                <button type="submit" class="btn btn-sm btn-light-secondary">{{ $note->done_at ? __('Not done') : __('Done') }}</button>
                            </form>
                        @endif
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endif
