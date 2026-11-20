<?php

namespace App\Http\Controllers;

use App\Exceptions\UserError;
use App\Models\Account;
use App\Models\AccountGroup;
use App\Models\Branch;
use App\Models\Currency;
use App\Models\JournalLine;
use App\Models\Party;
use App\Services\Accounts;
use App\Services\Ledger;
use App\Services\Rates;
use App\Services\Vouchers;
use App\Support\Num;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * The page of one account category: /accounts/cash, /accounts/customers, /accounts/<any category>.
 * Every category has the same list and the same form; the address says which category it is.
 * An account holds a separate balance in every currency it uses.
 */
class PartyController extends CrudController
{
    protected string $model = Party::class;

    protected string $route = 'accounts';

    protected string $permission = 'accounts';

    protected string $title = 'Accounts';

    protected string $singular = 'Account';

    protected string $icon = 'address-book';

    protected array $search = ['name', 'phone', 'address'];

    protected array $with = ['group', 'accounts.total', 'accounts.currency'];

    private ?AccountGroup $group = null;

    /** The category named in the address. An unknown or switched-off category is "not found". */
    protected function group(): AccountGroup
    {
        return $this->group ??= AccountGroup::active()->where('key', (string) request()->route('group'))->firstOrFail();
    }

    public function callAction($method, $parameters)
    {
        $this->group = null;
        $group = $this->group();
        $this->title = $group->label();
        $this->icon = $group->icon;
        // "accounts" covers every category; a user may also be given this category alone
        $user = request()->user();
        $own = 'group-'.$group->key;
        $this->permission = $user && ! $user->can('accounts.manage') && ($user->can($own.'.manage') || (! $user->can('accounts.view') && $user->can($own.'.view'))) ? $own : 'accounts';

        return parent::callAction($method, $parameters);
    }

    protected function routeParams(): array
    {
        return ['group' => $this->group()->key];
    }

    protected function query(Request $request): Builder
    {
        return Party::query()->where('account_group_id', $this->group()->id)
            ->when($request->query('state') === 'on', fn ($q) => $q->where('is_active', true))
            ->when($request->query('state') === 'off', fn ($q) => $q->where('is_active', false))
            ->when($request->query('currency'), fn ($q, $id) => $q->whereHas('accounts', fn ($a) => $a->where('currency_id', $id)));
    }

    protected function filters(Request $request): array
    {
        return [
            ['name' => 'currency', 'label' => __('Currency'), 'value' => $request->query('currency'), 'options' => Currency::active()->orderBy('sort')->pluck('code', 'id')->all()],
            ['name' => 'state', 'label' => __('In use'), 'value' => $request->query('state'), 'options' => ['on' => __('In use'), 'off' => __('Switched off')]],
        ];
    }

    protected function fields(?Model $record): array
    {
        $money = $this->group()->isMoney();
        $fields = [
            ['name' => 'name', 'label' => 'Name', 'rules' => ['required', 'string', 'max:150']],
            ['name' => 'phone', 'label' => 'Phone', 'rules' => ['nullable', 'string', 'max:50']],
            ['name' => 'address', 'label' => 'Address', 'col' => 12, 'rules' => ['nullable', 'string', 'max:255']],
        ];

        if (! $money) {
            $fields[] = ['name' => 'credit_limit', 'label' => 'Credit limit', 'type' => 'decimal', 'rules' => ['nullable', 'numeric', 'gte:0'],
                'help' => __('The most this account may owe the business, valued in :currency. Empty: no limit.', ['currency' => Currency::base()->code])];
        }

        // opening balances are given once, when the account is made; afterwards money moves with papers
        if (! $record) {
            $fields[] = ['name' => 'currencies', 'label' => 'Currencies and opening balances', 'type' => 'currencies', 'virtual' => true, 'col' => 12,
                'options' => Currency::active()->orderBy('sort')->pluck('code', 'id')->all(), 'default' => Currency::base()->id,
                'sides' => $money ? ['debit' => __('Balance')] : ['credit' => __('We owe them'), 'debit' => __('They owe us')],
                'side' => $money ? 'debit' : 'credit', 'rules' => ['nullable', 'array'],
                'help' => 'Tick the currencies this account uses. Leave a balance empty when the account starts with nothing.'];
        }

        $fields[] = ['name' => 'notes', 'label' => 'Notes', 'type' => 'textarea', 'col' => 12, 'rules' => ['nullable', 'string', 'max:2000']];
        $fields[] = ['name' => 'is_active', 'label' => 'In use', 'type' => 'checkbox', 'default' => true];

        return $fields;
    }

    protected function columns(): array
    {
        $money = $this->group()->isMoney();

        return [
            'Name' => fn (Party $p) => $p->name,
            'Phone' => fn (Party $p) => $p->phone,
            'Currency' => fn (Party $p) => '—',
            'Balance' => fn (Party $p) => '—',
            'In use' => fn (Party $p) => $p->is_active ? __('Yes') : __('No'),
        ];
    }

    protected array $splitColumns = ['Currency', 'Balance'];

    /** One line for each currency the account keeps, its balance in the colour of what it means. */
    protected function splitRows(Model $record, Request $request): array
    {
        $record->loadMissing(['accounts.total', 'accounts.currency', 'group']);
        $money = $record->group->isMoney();
        $only = (int) $request->query('currency');
        $lines = [];
        foreach ($record->accounts->sortBy(fn ($a) => [$a->currency?->sort, $a->currency_id]) as $account) {
            if ($only && (int) $account->currency_id !== $only) {
                continue;
            }
            $balance = Num::money($account->total?->balance ?? 0);
            // a money account holds a debit balance; a party we owe holds a credit balance
            $shown = $money ? $balance : Num::money(Num::neg($balance));
            [$tag, $tone] = match (true) {
                Num::isZero($shown) => [__('settled'), 'zero'],
                $money => Num::isPositive($shown) ? [null, 'in'] : [__('overdrawn'), 'out'],
                default => Num::isPositive($shown) ? [__('we owe'), 'out'] : [__('owes us'), 'in'],
            };
            $lines[] = [
                'Currency' => ['text' => $account->currency->code, 'class' => 'cur-cell', 'url' => route('accounts.statement', $this->routeParams() + ['id' => $record->id, 'currency' => $account->currency_id])],
                'Balance' => ['text' => money(Num::abs($shown)), 'class' => 'num bal-cell amt-'.$tone, 'tag' => $tag, 'tone' => $tone],
            ];
        }

        return $lines;
    }

    protected function rowLinks(Model $record): array
    {
        return [
            ['label' => __('Statement'), 'url' => route('accounts.statement', $this->routeParams() + ['id' => $record->id])],
            ['label' => __('Notes'), 'url' => route('accounts.notes', $this->routeParams() + ['id' => $record->id]), 'modal' => true],
            ['label' => __('Files'), 'url' => route('attachments.index', ['type' => 'party', 'id' => $record->id]), 'modal' => true],
        ];
    }

    protected function beforeSave(array $data, ?Model $record, Request $request): array
    {
        if (! $record) {
            // the plan of the business says how many accounts it may have
            $limit = (int) (tenant()?->max_accounts ?? 0);
            if ($limit > 0 && Party::count() >= $limit) {
                throw new UserError(__('Your plan allows :count accounts. Ask Fida Technologies for a larger plan to add more.', ['count' => $limit]));
            }
            $data['account_group_id'] = $this->group()->id;
        }

        return $data;
    }

    protected function afterSave(Model $record, array $virtual, Request $request, bool $created): void
    {
        // the ledger accounts carry the name of their party
        Account::where('party_id', $record->id)->update(['name' => $record->name, 'is_active' => $record->is_active]);
        if ($record->phone && Party::where('phone', $record->phone)->where('id', '!=', $record->id)->exists()) {
            session()->flash('warning', __('Another account has the same phone number (:phone). Check that it is not the same person twice.', ['phone' => $record->phone]));
        }
        if (! $created) {
            return;
        }

        $branch = current_branch();
        $given = $request->validate(['currencies' => ['nullable', 'array'], 'currencies.*.opening' => ['nullable', 'numeric', 'gte:0'], 'currencies.*.side' => ['nullable', 'in:debit,credit']])['currencies'] ?? [];
        $ticked = array_filter((array) $request->input('currencies', []), fn ($row) => ! empty($row['on']));
        if (! $ticked) {
            $ticked = [Currency::base()->id => []];
        }

        foreach (Currency::active()->whereIn('id', array_keys($ticked))->orderBy('sort')->get() as $currency) {
            $account = Accounts::party($record, $currency, $branch);
            $opening = Num::money($given[$currency->id]['opening'] ?? 0);
            if (! Num::isPositive($opening)) {
                continue;
            }
            $debit = $this->group()->isMoney() || ($given[$currency->id]['side'] ?? 'credit') === 'debit';
            $base = Rates::toBase($opening, $currency, $branch);
            $equity = Accounts::system($branch, 'opening');
            Ledger::post($branch, 'opening', today_iso(), __('Opening balance').' · '.$record->name, [
                ['account' => $account, $debit ? 'debit' : 'credit' => $opening, 'base' => $base],
                ['account' => $equity, $debit ? 'credit' : 'debit' => $base],
            ], $record);
        }
    }

    protected function deleteProblem(Model $record): ?string
    {
        if ($record->is_system) {
            return __('The cash box of the business cannot be deleted.');
        }
        if (JournalLine::whereIn('account_id', $record->allAccounts()->pluck('id'))->exists()) {
            return __('This account has entries. Switch it off instead.');
        }
        // its empty ledger accounts go with it
        $record->allAccounts()->delete();

        return null;
    }


    /** Every line of one account in one currency between two dates, with the balance after each. */
    /** @return array<string, mixed> the account, its currency, the period and its lines with the balance after each */
    private function statementData(Request $request): array
    {

        $party = Party::with(['group', 'accounts.currency', 'accounts.total'])->where('account_group_id', $this->group()->id)->findOrFail($request->route('id'));
        $filters = $request->validate(['from' => ['nullable', 'date'], 'to' => ['nullable', 'date'], 'currency' => ['nullable', 'integer']]);
        $from = $filters['from'] ?? now()->startOfMonth()->toDateString();
        $to = $filters['to'] ?? today_iso();

        $accounts = $party->accounts->sortBy('currency_id')->values();
        $account = $accounts->firstWhere('currency_id', (int) ($filters['currency'] ?? 0))
            ?? $accounts->first(fn (Account $a) => ! Num::isZero($a->total?->balance ?? 0))
            ?? $accounts->first();
        if (! $account) {
            // an account nothing was recorded on yet: its statement opens in the base currency, empty
            $account = Accounts::party($party, Currency::base());
            $accounts = collect([$account->load('currency')]);
        }

        // a party is read from its own side: what the business owes it is a positive balance
        $sign = $party->group->isMoney() ? '1' : '-1';
        $opening = Num::money(Num::mul(Ledger::balanceOn($account->id, $from, true), $sign));
        $lines = JournalLine::with('entry')
            ->where('account_id', $account->id)->where('is_void', false)
            ->whereDayOf('date', '>=', $from)->whereDayOf('date', '<=', $to)
            ->orderBy('date')->orderBy('id')->limit(5000)->get();

        $running = $opening;
        $rows = [];
        foreach ($lines as $line) {
            $running = Num::money(Num::add($running, Num::mul(Num::sub($line->debit, $line->credit), $sign)));
            $rows[] = ['line' => $line, 'balance' => $running];
        }

        return [
            'party' => $party, 'group' => $party->group, 'account' => $account, 'accounts' => $accounts,
            'from' => $from, 'to' => $to, 'opening' => $opening, 'rows' => $rows, 'closing' => $running,
        ];
    }

    public function statement(Request $request): View
    {
        Gate::authorize($this->permission.'.view');
        $data = $this->statementData($request);
        $message = \App\Services\Whatsapp::statement($data['party'], $data['account'], $data['from'], $data['to'], $data['opening'], $data['rows'], $data['closing']);

        return view('accounts.statement', $data + [
            'whatsapp' => ['phone' => $data['party']->phone, 'sender' => \App\Services\Whatsapp::enabled(), 'link' => \App\Services\Whatsapp::link($data['party']->phone, $message),
                'balanceLink' => \App\Services\Whatsapp::link($data['party']->phone, $this->balanceMessage($data['party']))],
        ]);
    }

    /** The statement, or the balance of every currency alone, sent to the account on WhatsApp by the sender of the business. */
    public function whatsapp(Request $request): \Illuminate\Http\RedirectResponse
    {
        Gate::authorize($this->permission.'.view');
        $data = $this->statementData($request);
        $party = $data['party'];
        if (! filled($party->phone)) {
            return back()->with('danger', __(':name has no phone number. Add it to the account first.', ['name' => $party->name]));
        }
        $message = $request->input('what') === 'balance' ? $this->balanceMessage($party)
            : \App\Services\Whatsapp::statement($party, $data['account'], $data['from'], $data['to'], $data['opening'], $data['rows'], $data['closing']);
        if (! \App\Services\Whatsapp::send($party->phone, $message)) {
            return back()->with('danger', __('The message could not be sent. Check the WhatsApp settings, or open it in WhatsApp yourself.'));
        }
        audit('sent', $party, ($request->input('what') === 'balance' ? 'Balance' : 'Statement').' on WhatsApp to '.$party->phone);

        return back()->with('success', __('Sent to :name on WhatsApp.', ['name' => $party->name]));
    }

    private function balanceMessage(\App\Models\Party $party): string
    {
        return (setting('company_name') ?: tenant()?->name)."\n".__('Balance of :name on :date', ['name' => $party->name, 'date' => fmt_date(today_iso())]).":\n".\App\Services\Whatsapp::balance($party);
    }
}
