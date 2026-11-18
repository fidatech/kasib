<?php

namespace App\Http\Controllers;

use App\Exceptions\UserError;
use App\Models\Account;
use App\Models\AccountGroup;
use App\Models\Branch;
use App\Models\Hawala;
use App\Models\Party;
use App\Services\Accounts;
use App\Services\Vouchers;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Hawala tracking. Money sent through a saraf is written with the number the saraf gave, the
 * receiver and the city, and stays "on the way" until somebody marks it delivered. When it is
 * sent for a customer or a supplier, the money out is recorded with it.
 */
class HawalaController extends CrudController
{
    protected string $model = Hawala::class;

    protected string $route = 'hawalas';

    protected string $permission = 'transactions';

    protected string $title = 'Hawala Tracking';

    protected string $singular = 'Hawala';

    protected string $icon = 'paper-plane';

    protected array $search = ['number', 'receiver', 'receiver_phone', 'destination'];

    protected array $with = ['account.currency', 'party'];

    protected string $orderBy = 'id';

    protected string $orderDirection = 'desc';

    protected function query(Request $request): Builder
    {
        $status = $request->query('status');

        return Hawala::query()->when(isset(Hawala::STATUSES[$status]), fn ($q) => $q->where('status', $status));
    }

    protected function filters(Request $request): array
    {
        return [['name' => 'status', 'label' => __('Status'), 'options' => array_map(fn ($label) => __($label), Hawala::STATUSES), 'value' => $request->query('status')]];
    }

    protected function fields(?Model $record): array
    {
        $accounts = Accounts::money(current_branch());
        $parties = Party::active()->whereHas('group', fn ($q) => $q->where('type', AccountGroup::PARTY))->orderBy('name')->limit(2000)->get();

        return [
            ['name' => 'date', 'label' => 'Date', 'type' => 'date', 'default' => today_iso(), 'rules' => ['required', 'date']],
            ['name' => 'number', 'label' => 'Hawala number', 'rules' => ['required', 'string', 'max:60'], 'help' => 'The number or code the saraf gave.'],
            ['name' => 'account_id', 'label' => 'Sent through', 'type' => 'select',
                'options' => $accounts->mapWithKeys(fn (Account $a) => [$a->id => $a->label().' · '.$a->currency->code])->all(), 'rules' => ['required', Rule::in($accounts->pluck('id')->all())],
                'help' => 'The saraf or bank account the money went out of. The amount is in its currency.'],
            ['name' => 'amount', 'label' => 'Amount', 'type' => 'decimal', 'rules' => ['required', 'numeric', 'gt:0']],
            ['name' => 'receiver', 'label' => 'Receiver', 'rules' => ['required', 'string', 'max:150']],
            ['name' => 'receiver_phone', 'label' => 'Phone of the receiver', 'rules' => ['nullable', 'string', 'max:50']],
            ['name' => 'destination', 'label' => 'City or country', 'rules' => ['nullable', 'string', 'max:150']],
            ['name' => 'party_id', 'label' => 'Sent for (account)', 'type' => 'select', 'options' => $parties->mapWithKeys(fn (Party $p) => [$p->id => $p->name])->all(),
                'rules' => ['nullable', Rule::in($parties->pluck('id')->all())],
                'help' => 'Choose the customer or supplier and the money out is recorded on his account with the hawala. Leave it empty to only follow the hawala.'],
            ['name' => 'notes', 'label' => 'Details', 'col' => 12, 'rules' => ['nullable', 'string', 'max:500']],
        ];
    }

    protected function columns(): array
    {
        return [
            'Date' => fn (Hawala $h) => fmt_date($h->date),
            'Hawala number' => fn (Hawala $h) => $h->number,
            'Sent through' => fn (Hawala $h) => $h->account->label(),
            'Amount' => fn (Hawala $h) => money($h->amount, $h->account->currency->code),
            'Receiver' => fn (Hawala $h) => trim($h->receiver.' '.$h->receiver_phone),
            'City or country' => fn (Hawala $h) => $h->destination,
            'Sent for (account)' => fn (Hawala $h) => $h->party?->name,
            'Status' => fn (Hawala $h) => __(Hawala::STATUSES[$h->status] ?? $h->status).($h->delivered_on ? ' · '.fmt_date($h->delivered_on) : ''),
        ];
    }

    protected function beforeSave(array $data, ?Model $record, Request $request): array
    {
        if ($record) {
            throw new UserError(__('A hawala is not changed. Cancel it and write it again.'));
        }

        return $data + ['status' => 'sent', 'created_by' => $request->user()->id];
    }

    protected function afterSave(Model $record, array $virtual, Request $request, bool $created): void
    {
        if ($record->party_id) {
            $voucher = Vouchers::moneyOut($record->party, $record->account, $record->date->toDateString(), $record->amount,
                trim(__('Hawala').' '.$record->number.' · '.$record->receiver.' · '.$record->destination, ' ·'));
            $record->update(['voucher_id' => $voucher->id]);
        }
        audit('created', null, 'Hawala '.$record->number.' '.$record->amount);
    }

    protected function canEdit(Model $record): bool
    {
        return false;
    }

    protected function deleteProblem(Model $record): ?string
    {
        return __('A hawala is not deleted. Cancel it and it stays on the list.');
    }

    protected function rowLinks(Model $record): array
    {
        if ($record->status !== 'sent' || ! auth()->user()->can('transactions.manage')) {
            return [];
        }

        return [
            ['label' => __('Delivered'), 'url' => route('hawalas.deliver', ['id' => $record->id]), 'post' => true],
            ['label' => __('Cancel'), 'url' => route('hawalas.cancel', ['id' => $record->id]), 'post' => true, 'confirm' => __('Cancel this hawala? Money out recorded with it is taken back.')],
        ];
    }

    public function deliver(Request $request): RedirectResponse
    {
        Gate::authorize('transactions.manage');
        $hawala = Hawala::where('status', 'sent')->findOrFail((int) $request->route('id'));
        $hawala->update(['status' => 'delivered', 'delivered_on' => today_iso()]);
        audit('updated', null, 'Hawala '.$hawala->number.' delivered');

        return back()->with('success', __('The hawala was marked as delivered.'));
    }

    public function cancel(Request $request): RedirectResponse
    {
        Gate::authorize('transactions.manage');
        $hawala = Hawala::with('voucher')->where('status', 'sent')->findOrFail((int) $request->route('id'));
        DB::transaction(function () use ($hawala) {
            if ($hawala->voucher) {
                Vouchers::cancel($hawala->voucher, __('Hawala').' '.$hawala->number);
            }
            $hawala->update(['status' => 'cancelled']);
        });
        audit('cancelled', null, 'Hawala '.$hawala->number);

        return back()->with('success', __('The hawala was cancelled.'));
    }
}
