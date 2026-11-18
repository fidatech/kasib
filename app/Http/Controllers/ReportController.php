<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\AccountCategory;
use App\Models\AccountGroup;
use App\Models\Currency;
use App\Models\Party;
use App\Models\Voucher;
use App\Services\Vouchers;
use App\Support\Num;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * The reports. All of them are read from the journal, so they agree with each other:
 * the trial balance, the profit and loss statement, the balance sheet, what customers and
 * suppliers have with the business, and the money that is due to be paid back.
 */
class ReportController extends Controller
{
    /** route name => [title, icon, what it shows] */
    public const REPORTS = [
        'reports.trial' => ['Trial Balance', 'scale-balanced', 'Every account with a balance, debit and credit.'],
        'reports.profit' => ['Profit & Loss', 'chart-line', 'Income and expenses between two dates, and the profit.'],
        'reports.balance' => ['Balance Sheet', 'landmark', 'What the business has, what it owes and its capital on a date.'],
        'reports.balances' => ['Customer & Supplier Balances', 'users', 'What every party has with the business, per currency.'],
        'reports.due' => ['Due Payments', 'calendar-days', 'Money received with a payback date that has come or is near.'],
        'treasury.index' => ['Currency Position', 'vault', 'The money of the business in every currency, and where it is.'],
        'journal.index' => ['Roznamcha', 'book', 'The general ledger: every entry of every transaction.'],
    ];

    public function index(): View
    {
        Gate::authorize('reports.view');

        return view('reports.index', ['reports' => self::REPORTS]);
    }

    public function trial(): View
    {
        Gate::authorize('reports.view');

        $accounts = Account::with(['category', 'currency', 'total'])
            ->whereHas('total', fn ($q) => $q->where(fn ($i) => $i->where('balance', '!=', 0)->orWhere('base_balance', '!=', 0)))
            ->orderBy('account_category_id')->orderBy('name')
            ->get();

        $debit = '0';
        $credit = '0';
        $rows = [];
        foreach ($accounts as $account) {
            $base = $account->total->base_balance;
            $isDebit = ! Num::isNegative($base);
            $debit = Num::add($debit, $isDebit ? $base : '0');
            $credit = Num::add($credit, $isDebit ? '0' : Num::abs($base));
            $rows[] = ['account' => $account, 'debit' => $isDebit ? Num::money($base) : null, 'credit' => $isDebit ? null : Num::money(Num::abs($base))];
        }

        return view('reports.trial', [
            'groups' => collect($rows)->groupBy(fn (array $row) => $row['account']->category->nature),
            'natures' => AccountCategory::NATURES,
            'debit' => Num::money($debit), 'credit' => Num::money($credit),
            'base' => Currency::base()->code,
        ]);
    }

    /** Income and expenses between two dates, group by group, in the base currency. */
    public function profit(Request $request): View
    {
        Gate::authorize('reports.view');
        [$from, $to] = $this->dates($request);
        $sums = $this->movement($from, $to);

        $rows = [];
        $totals = ['income' => '0', 'expense' => '0'];
        foreach (['income' => 'Income', 'expense' => 'Expenses'] as $nature => $heading) {
            $rows[] = ['heading' => __($heading)];
            foreach ($sums->where('nature', $nature) as $sum) {
                $amount = $nature === 'income' ? Num::sub($sum->credit, $sum->debit) : Num::sub($sum->debit, $sum->credit);
                if (Num::isZero(Num::money($amount))) {
                    continue;
                }
                $rows[] = ['cells' => [$this->categoryName($sum), money($amount)]];
                $totals[$nature] = Num::add($totals[$nature], $amount);
            }
            $rows[] = ['total' => true, 'cells' => [__('Total :name', ['name' => mb_strtolower(__($heading))]), money($totals[$nature])]];
        }
        $profit = Num::money(Num::sub($totals['income'], $totals['expense']));
        $rows[] = ['total' => true, 'cells' => [Num::isNegative($profit) ? __('Loss of the period') : __('Profit of the period'), money(Num::abs($profit))]];

        return $this->sheet('Profit & Loss', 'chart-line', [['', ''], [Currency::base()->code, 'end']], $rows, $from, $to);
    }

    /**
     * What the business has, what it owes and its capital at the end of a date. A customer or a
     * supplier who owes the business is shown with the assets; one the business owes, with the
     * liabilities. Profit that is not closed into the capital yet has its own line.
     */
    public function balance(Request $request): View
    {
        Gate::authorize('reports.view');
        $to = $this->dates($request)[1];

        $accounts = DB::table('journal_lines as l')
            ->join('accounts as a', 'a.id', '=', 'l.account_id')
            ->join('account_categories as c', 'c.id', '=', 'a.account_category_id')
            ->where('l.is_void', false)->whereDate('l.date', '<=', $to)
            ->groupBy('a.id', 'c.id', 'c.name', 'c.name_local', 'c.nature', 'c.sort')
            ->selectRaw('a.id as account, c.id as category, c.name as name, c.name_local as name_local, c.nature as nature, c.sort as sort, SUM(l.base_debit) as debit, SUM(l.base_credit) as credit')
            ->orderBy('c.sort')->get();
        $partyGroups = AccountGroup::where('type', AccountGroup::PARTY)->pluck('account_category_id')->map(fn ($id) => (int) $id)->all();

        // [side][label] => amount; the side of a party account follows its balance
        $sides = ['asset' => [], 'liability' => [], 'equity' => []];
        $result = '0';
        foreach ($accounts as $row) {
            $net = Num::sub($row->debit, $row->credit);   // debits minus credits
            if (in_array($row->nature, ['income', 'expense'], true)) {
                $result = Num::sub($result, $net);
                continue;
            }
            $side = $row->nature;
            $label = $this->categoryName($row);
            if (in_array((int) $row->category, $partyGroups, true)) {
                $side = Num::isPositive($net) ? 'asset' : 'liability';
                $label .= ' · '.($side === 'asset' ? __('they owe us') : __('we owe them'));
            }
            $amount = $side === 'asset' ? $net : Num::neg($net);
            $sides[$side][$label] = Num::add($sides[$side][$label] ?? '0', $amount);
        }
        $sides['equity'][__('Profit not closed yet')] = $result;

        $rows = [];
        $totals = [];
        foreach (['asset' => 'Assets', 'liability' => 'Liabilities', 'equity' => 'Capital'] as $side => $heading) {
            $rows[] = ['heading' => __($heading)];
            $totals[$side] = '0';
            foreach ($sides[$side] as $label => $amount) {
                if (Num::isZero(Num::money($amount))) {
                    continue;
                }
                $rows[] = ['cells' => [$label, money($amount)]];
                $totals[$side] = Num::add($totals[$side], $amount);
            }
            $rows[] = ['total' => true, 'cells' => [__('Total :name', ['name' => mb_strtolower(__($heading))]), money($totals[$side])]];
        }
        $rows[] = ['total' => true, 'cells' => [__('Liabilities and capital'), money(Num::add($totals['liability'], $totals['equity']))]];

        return $this->sheet('Balance Sheet', 'landmark', [['', ''], [Currency::base()->code, 'end']], $rows, null, $to);
    }

    /** What every customer, supplier and other party has with the business, per currency. */
    public function balances(Request $request): View
    {
        Gate::authorize('reports.view');
        $groupId = (int) $request->query('group');
        $groups = AccountGroup::active()->where('type', AccountGroup::PARTY)->orderBy('sort')->get();

        $rows = [];
        $parties = Party::with(['group', 'accounts.total', 'accounts.currency'])
            ->whereIn('account_group_id', $groupId ? [$groupId] : $groups->pluck('id'))->orderBy('account_group_id')->orderBy('name')->limit(5000)->get();
        $sums = [];
        foreach ($parties as $party) {
            foreach (Vouchers::balances($party) as $balance) {
                $owed = Num::isPositive($balance['amount']);
                $code = $balance['currency']->code;
                $rows[] = ['cells' => [
                    ['text' => $party->name, 'url' => route('accounts.statement', ['group' => $party->group->key, 'id' => $party->id, 'currency' => $balance['currency']->id])],
                    $party->group->label(), (string) $party->phone, $code,
                    $owed ? money($balance['amount']) : '', $owed ? '' : money(Num::abs($balance['amount'])),
                ]];
                $sums[$code] ??= ['0', '0'];
                $sums[$code][$owed ? 0 : 1] = Num::add($sums[$code][$owed ? 0 : 1], Num::abs($balance['amount']));
            }
        }
        foreach ($sums as $code => [$weOwe, $theyOwe]) {
            $rows[] = ['total' => true, 'cells' => [__('Total'), '', '', $code, money($weOwe), money($theyOwe)]];
        }

        return $this->sheet('Customer & Supplier Balances', 'users', [['Account'], ['Category'], ['Phone'], ['Currency'], ['We owe them', 'end'], ['They owe us', 'end']], $rows, null, null,
            [['name' => 'group', 'label' => 'Category', 'options' => $groups->mapWithKeys(fn (AccountGroup $g) => [$g->id => $g->label()])->all()]]);
    }

    /** Money received with a payback date: what is overdue, and what falls due in the days ahead. */
    public function due(Request $request): View
    {
        Gate::authorize('reports.view');
        $days = max(0, min(365, (int) $request->query('days', 7)));
        $until = now()->addDays($days)->toDateString();

        $rows = [];
        $vouchers = Voucher::with(['party.group', 'party.accounts.total', 'currency'])->where('kind', Voucher::MONEY_IN)
            ->whereNull('cancelled_at')->whereNotNull('due_date')->whereDate('due_date', '<=', $until)->orderBy('due_date')->limit(5000)->get();
        foreach ($vouchers as $voucher) {
            // nothing is due to a party the business no longer owes in that currency
            $account = $voucher->party?->accounts->firstWhere('currency_id', $voucher->currency_id);
            $owed = Num::neg($account?->total?->balance ?? 0);
            if (! $voucher->party || ! Num::isPositive($owed)) {
                continue;
            }
            $late = (int) round((strtotime(today_iso()) - strtotime($voucher->due_date->toDateString())) / 86400);
            $rows[] = ['cells' => [
                fmt_date($voucher->due_date),
                $late > 0 ? __(':days days late', ['days' => $late]) : ($late === 0 ? __('Today') : __('In :days days', ['days' => -$late])),
                ['text' => $voucher->party->name, 'url' => route('accounts.statement', ['group' => $voucher->party->group->key, 'id' => $voucher->party->id, 'currency' => $voucher->currency_id])],
                (string) $voucher->party->phone, $voucher->number, money($voucher->amount, $voucher->currency->code), money($owed, $voucher->currency->code),
            ]];
        }

        return $this->sheet('Due Payments', 'calendar-days', [['To be paid back on'], ['Status'], ['Account'], ['Phone'], ['No.'], ['Amount received', 'end'], ['We owe them now', 'end']], $rows, null, null,
            [['name' => 'days', 'label' => 'Falling due within', 'options' => [0 => __('Overdue and today'), 7 => __('7 days'), 30 => __('30 days'), 90 => __('90 days')], 'value' => $days]]);
    }

    private function sheet(string $title, string $icon, array $columns, array $rows, ?string $from, ?string $to, array $filters = []): View
    {
        return view('reports.sheet', ['title' => __($title), 'icon' => $icon, 'columns' => $columns, 'rows' => $rows, 'from' => $from, 'to' => $to, 'filters' => $filters]);
    }

    /** @return array{0: string, 1: string} from and to, the year so far when nothing was asked */
    private function dates(Request $request): array
    {
        $f = $request->validate(['from' => ['nullable', 'date'], 'to' => ['nullable', 'date']]);

        return [$f['from'] ?? now()->startOfYear()->toDateString(), $f['to'] ?? today_iso()];
    }

    /** Debits and credits of every income and expense group between two dates. The entry that closes a period is left out. */
    private function movement(string $from, string $to)
    {
        return DB::table('journal_lines as l')
            ->join('journal_entries as e', 'e.id', '=', 'l.journal_entry_id')
            ->join('accounts as a', 'a.id', '=', 'l.account_id')
            ->join('account_categories as c', 'c.id', '=', 'a.account_category_id')
            ->where('l.is_void', false)->whereIn('c.nature', ['income', 'expense'])->where('e.type', '!=', 'closing')
            ->whereDate('l.date', '>=', $from)->whereDate('l.date', '<=', $to)
            ->groupBy('c.id', 'c.name', 'c.name_local', 'c.nature', 'c.sort')->orderBy('c.sort')
            ->selectRaw('c.name as name, c.name_local as name_local, c.nature as nature, SUM(l.base_debit) as debit, SUM(l.base_credit) as credit')->get();
    }

    private function categoryName(object $row): string
    {
        return (text_dir() === 'rtl' ? $row->name_local : null) ?: __($row->name);
    }
}
