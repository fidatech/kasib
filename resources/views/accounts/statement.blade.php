@extends(request()->boolean('print') ? 'layouts.print' : 'layouts.app', ['title' => __('Statement')])

@section('content')
<x-page-header :title="$party->name" :icon="$group->icon" :crumbs="[[$group->label(), route('accounts.index', ['group' => $group->key])], [__('Statement')]]" :subtitle="trim($group->label().' · '.$account->currency->code.($party->phone ? ' · '.$party->phone : ''))">
    @unless (request()->boolean('print'))
        @php($params = ['group' => $group->key, 'id' => $party->id])
        @php($query = ['currency' => $account->currency_id, 'from' => $from, 'to' => $to])
        @if (! filled($party->phone))
            <span class="d-inline-block" tabindex="0" title="{{ __('Add a phone number to the account to send it on WhatsApp.') }}"><button type="button" class="btn btn-wa" disabled><x-icon name="comment-dots" /> {{ __('WhatsApp') }}</button></span>
        @else
            <div class="dropdown">
                <button type="button" class="btn btn-wa dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false"><x-icon name="comment-dots" /> {{ __('WhatsApp') }}</button>
                <ul class="dropdown-menu dropdown-menu-end">
                    @if ($whatsapp['sender'])
                        <li><form method="POST" action="{{ route('accounts.statement.whatsapp', $params + $query) }}">@csrf<input type="hidden" name="what" value="statement"><button type="submit" class="dropdown-item"><x-icon name="file-lines" /> {{ __('Send this statement') }}</button></form></li>
                        <li><form method="POST" action="{{ route('accounts.statement.whatsapp', $params + $query) }}">@csrf<input type="hidden" name="what" value="balance"><button type="submit" class="dropdown-item"><x-icon name="scale-balanced" /> {{ __('Send the balance of every currency') }}</button></form></li>
                    @else
                        <li><a class="dropdown-item" href="{{ $whatsapp['link'] }}" target="_blank" rel="noopener"><x-icon name="file-lines" /> {{ __('Send this statement') }}</a></li>
                        <li><a class="dropdown-item" href="{{ $whatsapp['balanceLink'] }}" target="_blank" rel="noopener"><x-icon name="scale-balanced" /> {{ __('Send the balance of every currency') }}</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><span class="dropdown-item-text small text-muted" style="max-width:280px;white-space:normal">{{ __('Opens WhatsApp with the message written. With a WhatsApp sender in the settings it is sent from here at once.') }}</span></li>
                    @endif
                </ul>
            </div>
        @endif
    @endunless
</x-page-header>

@if (! request()->boolean('print') && ! $group->isMoney() && \App\Support\Permissions::group(auth()->user(), $group->key, 'manage'))
    <div class="card mb-3 no-print">
        <div class="card-body d-flex flex-wrap gap-2 align-items-center">
            <span class="fw-semibold"><x-icon name="link" class="me-1" /> {{ __('Private link for the customer') }}</span>
            @if ($party->share_token)
                <input type="text" readonly class="form-control form-control-sm" dir="ltr" style="max-width: 30rem" value="{{ route('statement.shared', ['token' => $party->share_token]) }}" onfocus="this.select()" aria-label="{{ __('Private link for the customer') }}">
                <form method="POST" action="{{ route('accounts.link', ['group' => $group->key, 'id' => $party->id]) }}" class="d-inline" data-confirm="{{ __('Make a new link? The old one stops working.') }}" data-confirm-ok="{{ __('New link') }}">@csrf<button type="submit" class="btn btn-sm btn-light-secondary">{{ __('New link') }}</button></form>
                <form method="POST" action="{{ route('accounts.unlink', ['group' => $group->key, 'id' => $party->id]) }}" class="d-inline">@csrf @method('DELETE')<button type="submit" class="btn btn-sm btn-light-secondary">{{ __('Switch it off') }}</button></form>
            @else
                <form method="POST" action="{{ route('accounts.link', ['group' => $group->key, 'id' => $party->id]) }}" class="d-inline">@csrf<button type="submit" class="btn btn-sm btn-outline-soft">{{ __('Make a link') }}</button></form>
            @endif
            <span class="small text-muted w-100">{{ __('Whoever has the link reads the balance and the lines of the last 90 days of this account, without signing in. Nothing can be changed from it.') }}</span>
        </div>
    </div>
@endif

<div class="card">
    <div class="card-body">
        <form method="GET" class="filter-bar row g-2 align-items-end mb-3 no-print">
            <div class="col-6 col-md-auto">
                <label class="form-label small mb-1" for="st-currency">{{ __('Currency') }}</label>
                <select class="form-select" id="st-currency" name="currency">
                    @foreach ($accounts as $one)
                        <option value="{{ $one->currency_id }}" @selected($one->id === $account->id)>{{ $one->currency->code }}</option>
                    @endforeach
                </select>
            </div>
            @include('partials.date-filter')
            <div class="col-auto"><button class="btn btn-light-secondary" type="submit">{{ __('Show') }}</button></div>
        </form>

        <table class="table table-hover w-100 keep-table">
            <thead>
                <tr>
                    <th>{{ __('Date') }}</th><th>{{ __('No.') }}</th><th>{{ __('Details') }}</th>
                    <th class="text-end">{{ $group->isMoney() ? __('In') : __('Paid to them') }}</th>
                    <th class="text-end">{{ $group->isMoney() ? __('Out') : __('Received from them') }}</th>
                    <th class="text-end">{{ $group->isMoney() ? __('Balance') : __('We owe them') }}</th>
                </tr>
            </thead>
            <tbody>
                <tr class="fw-semibold"><td colspan="5">{{ __('Balance before :date', ['date' => fmt_date($from)]) }}</td><td class="text-end text-nowrap">{{ money($opening, $account->currency->code) }}</td></tr>
                @foreach ($rows as $row)
                    <tr>
                        <td class="text-nowrap">{{ fmt_date($row['line']->date) }}</td>
                        <td class="text-nowrap">{{ $row['line']->entry->number }}</td>
                        <td>{{ $row['line']->memo ?: $row['line']->entry->description }}</td>
                        <td class="text-end text-nowrap">{{ \App\Support\Num::isZero($row['line']->debit) ? '' : money($row['line']->debit) }}</td>
                        <td class="text-end text-nowrap">{{ \App\Support\Num::isZero($row['line']->credit) ? '' : money($row['line']->credit) }}</td>
                        <td class="text-end text-nowrap">{{ money($row['balance']) }}</td>
                    </tr>
                @endforeach
                <tr class="fw-semibold"><td colspan="5">{{ __('Balance on :date', ['date' => fmt_date($to)]) }}</td><td class="text-end text-nowrap">{{ money($closing, $account->currency->code) }}</td></tr>
            </tbody>
        </table>
        @unless ($group->isMoney())
            <div class="form-text">{{ __('A balance below zero means they owe the business.') }}</div>
        @endunless
    </div>
</div>
@endsection
