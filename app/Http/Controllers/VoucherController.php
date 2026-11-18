<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\AccountCategory;
use App\Models\Branch;
use App\Models\Currency;
use App\Models\Party;
use App\Models\Voucher;
use App\Services\Accounts;
use App\Services\Vouchers;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * The list and the form shared by the daily papers: money in, money out, transfers, exchanges
 * and income. Each screen says which kind it is; the fields and the columns follow from that.
 */
abstract class VoucherController extends PaperController
{
    protected string $permission = 'transactions';

    protected string $kind;

    protected function rows(Request $request): LengthAwarePaginator
    {
        $f = $request->validate([
            'from' => ['nullable', 'date'], 'to' => ['nullable', 'date'], 'q' => ['nullable', 'string', 'max:100'],
            'party' => ['nullable', 'integer'], 'currency' => ['nullable', 'integer'], 'account' => ['nullable', 'integer'], 'user' => ['nullable', 'integer'],
            'min' => ['nullable', 'numeric'], 'max' => ['nullable', 'numeric'], 'state' => ['nullable', Rule::in(['active', 'cancelled'])],
        ]);

        return Voucher::with(['party', 'account.currency', 'otherAccount.currency', 'otherAccount.category', 'currency', 'otherCurrency', 'creator'])
            ->where('kind', $this->kind)
            ->when($f['from'] ?? null, fn ($q, $v) => $q->whereDate('date', '>=', $v))
            ->when($f['to'] ?? null, fn ($q, $v) => $q->whereDate('date', '<=', $v))
            ->when($f['party'] ?? null, fn ($q, $v) => $q->where('party_id', $v))
            ->when($f['currency'] ?? null, fn ($q, $v) => $q->where(fn ($w) => $w->where('currency_id', $v)->orWhere('other_currency_id', $v)))
            ->when($f['account'] ?? null, fn ($q, $v) => $q->where(fn ($w) => $w->where('account_id', $v)->orWhere('other_account_id', $v)))
            ->when($f['user'] ?? null, fn ($q, $v) => $q->where('created_by', $v))
            ->when($f['min'] ?? null, fn ($q, $v) => $q->where('amount', '>=', $v))
            ->when($f['max'] ?? null, fn ($q, $v) => $q->where('amount', '<=', $v))
            ->when(($f['state'] ?? null) === 'active', fn ($q) => $q->whereNull('cancelled_at'))
            ->when(($f['state'] ?? null) === 'cancelled', fn ($q) => $q->whereNotNull('cancelled_at'))
            ->when($f['q'] ?? null, fn ($q, $v) => $q->where(fn ($w) => $w->where('number', 'like', '%'.$v.'%')->orWhere('description', 'like', '%'.$v.'%')))
            ->orderByDesc('date')->orderByDesc('id')
            ->paginate(per_page(25))->withQueryString();
    }

    protected function find(int $id): Model
    {
        return Voucher::with(['party', 'account.currency', 'otherAccount.currency', 'otherAccount.category', 'currency', 'otherCurrency', 'creator'])->where('kind', $this->kind)->findOrFail($id);
    }

    protected function cancel(Model $paper, ?string $reason): void
    {
        Vouchers::cancel($paper, $reason);
    }

    /** The code printed on the receipt of a paper: nobody can make it without the key of the system. */
    public static function code(Voucher $voucher): string
    {
        return substr(hash_hmac('sha256', tenant()?->id.'|'.$voucher->number.'|'.$voucher->amount.'|'.$voucher->date->toDateString(), (string) config('app.key')), 0, 12);
    }

    /** The receipt, with the address where anyone can check that it is real. */
    public function receipt(Request $request): \Illuminate\View\View
    {
        $paper = $this->find((int) $request->route('id'));

        return parent::receipt($request)->with('verify', route('receipts.verify', ['number' => $paper->number, 'code' => self::code($paper)]));
    }

    protected function shared(): array
    {
        return parent::shared() + ['ownDate' => true, 'filters' => $this->filters(), 'attach' => true];
    }

    /** The pick lists above the list. Every paper can be found by date, currency, account, user, amount and words. */
    protected function filters(): array
    {
        $filters = [];
        if (in_array($this->kind, [Voucher::MONEY_IN, Voucher::MONEY_OUT], true)) {
            $filters[] = ['name' => 'party', 'label' => 'Account', 'options' => $this->parties()->mapWithKeys(fn (Party $p) => [$p->id => $p->name.' · '.$p->group->label()])->all()];
        }
        $filters[] = ['name' => 'account', 'label' => 'Cash, bank or saraf', 'options' => $this->moneyOptions()];
        $filters[] = ['name' => 'currency', 'label' => 'Currency', 'options' => Currency::active()->orderBy('sort')->pluck('code', 'id')->all()];
        $filters[] = ['name' => 'user', 'label' => 'User', 'options' => \App\Models\User::orderBy('name')->pluck('name', 'id')->all()];
        $filters[] = ['name' => 'state', 'label' => 'Status', 'options' => ['active' => __('Not cancelled'), 'cancelled' => __('Cancelled')]];

        return $filters;
    }

    protected function parties()
    {
        return Party::with('group')->active()->whereHas('group', fn ($q) => $q->where('type', 'party')->where('is_active', true))->orderBy('name')->get();
    }

    /** Money accounts as a pick list: "Cash · USD". */
    protected function moneyOptions(): array
    {
        return Accounts::money(current_branch())->mapWithKeys(fn (Account $a) => [$a->id => $a->label().' · '.$a->currency->code])->all();
    }

    protected function dateField(): array
    {
        return ['name' => 'date', 'label' => 'Date', 'type' => 'date', 'default' => today_iso(), 'col' => 3, 'rules' => ['required', 'date', 'before_or_equal:today']];
    }

    protected function partyField(): array
    {
        $parties = $this->parties();

        return ['name' => 'party_id', 'label' => 'Account', 'type' => 'select', 'search' => true, 'col' => 5,
            'options' => $parties->mapWithKeys(fn (Party $p) => [$p->id => $p->name.' · '.$p->group->label().($p->phone ? ' · '.$p->phone : '')])->all(),
            'rules' => ['required', Rule::in($parties->pluck('id')->all())], 'help' => 'A customer, a supplier or any other party.'];
    }

    protected function moneyField(string $name, string $label, ?string $help = null): array
    {
        $options = $this->moneyOptions();

        return ['name' => $name, 'label' => $label, 'type' => 'select', 'search' => true, 'col' => 4, 'default' => Accounts::cash(current_branch(), Currency::base())->id,
            'options' => $options, 'rules' => ['required', Rule::in(array_keys($options))]] + ($help ? ['help' => $help] : []);
    }

    protected function amountField(string $name = 'amount', string $label = 'Amount', ?string $help = null, bool $required = true): array
    {
        return ['name' => $name, 'label' => $label, 'type' => 'decimal', 'col' => 4, 'rules' => [$required ? 'required' : 'nullable', 'numeric', $required ? 'gt:0' : 'gte:0']] + ($help ? ['help' => $help] : []);
    }

    protected function detailsField(): array
    {
        return ['name' => 'description', 'label' => 'Details', 'col' => 12, 'rules' => ['nullable', 'string', 'max:500']];
    }

    protected function money(int|string $id): Account
    {
        return Account::with('currency')->findOrFail($id);
    }

    protected function saved(): string
    {
        return __(':name saved.', ['name' => __($this->singular)]);
    }

    protected function incomeCategories()
    {
        return AccountCategory::where('nature', 'income')->where('key', '!=', 'exchange_income')->orderBy('sort')->get();
    }
}
