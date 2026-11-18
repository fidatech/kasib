<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\AccountCategory;
use App\Models\AccountGroup;
use App\Models\Branch;
use App\Models\Currency;
use App\Models\Party;
use App\Models\Voucher;
use App\Services\Vouchers;
use App\Support\Num;
use Carbon\Carbon;
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
        'reports.forecast' => ['Cash Forecast', 'chart-line', 'The money expected in every currency after the paybacks and the recurring expenses ahead.'],
        'reports.alerts' => ['Unusual Activity', 'triangle-exclamation', 'Large, dated back, after-hours and cancelled papers, and budgets nearly used up.'],
        'export.index' => ['Export Center', 'file-excel', 'One Excel workbook of a period, with a sheet for every module you tick.'],
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

        $compare = in_array($request->query('compare'), ['previous', 'year'], true) ? $request->query('compare') : 'none';
        $filters = [['name' => 'compare', 'label' => 'Compare with', 'value' => $compare,
            'options' => ['none' => __('Nothing'), 'previous' => __('The period before'), 'year' => __('The same period a year before')]]];
        if ($compare === 'none') {
            return $this->sheet('Profit & Loss', 'chart-line', [['', ''], [Currency::base()->code, 'end']], $this->profitRows($from, $to), $from, $to, $filters);
        }
        // the period before: as many days, ending the day before this one starts
        $days = (int) round((strtotime($to) - strtotime($from)) / 86400) + 1;
        [$from2, $to2] = $compare === 'year'
            ? [Carbon::parse($from)->subYear()->toDateString(), Carbon::parse($to)->subYear()->toDateString()]
            : [Carbon::parse($from)->subDays($days)->toDateString(), Carbon::parse($from)->subDay()->toDateString()];

        return $this->sheet('Profit & Loss', 'chart-line', [['', ''], [fmt_date($from).' – '.fmt_date($to), 'end'], [fmt_date($from2).' – '.fmt_date($to2), 'end'], ['Change', 'end'], ['%', 'end']],
            $this->profitCompare($from, $to, $from2, $to2), $from, $to, $filters);
    }

    /** The profit and loss statement of two periods side by side, with the change of every line. */
    private function profitCompare(string $from, string $to, string $from2, string $to2): array
    {
        $now = $this->movement($from, $to);
        $then = $this->movement($from2, $to2);
        $line = function (string $name, mixed $a, mixed $b, bool $total = false): array {
            $change = Num::sub($a, $b);
            $percent = Num::isZero(Num::money($b)) ? '' : Num::round(Num::div(Num::mul($change, '100'), Num::abs($b)), 1).'%';

            return ['total' => $total, 'cells' => [$name, money($a), money($b), money($change), $percent]];
        };

        $rows = [];
        $totals = [];
        foreach (['income' => 'Income', 'expense' => 'Expenses'] as $nature => $heading) {
            $rows[] = ['heading' => __($heading)];
            $amount = fn (?object $sum) => $sum === null ? '0' : ($nature === 'income' ? Num::sub($sum->credit, $sum->debit) : Num::sub($sum->debit, $sum->credit));
            $a = $now->where('nature', $nature)->keyBy('id');
            $b = $then->where('nature', $nature)->keyBy('id');
            $totals[$nature] = ['0', '0'];
            foreach ($a->keys()->merge($b->keys())->unique() as $id) {
                [$x, $y] = [$amount($a[$id] ?? null), $amount($b[$id] ?? null)];
                if (Num::isZero(Num::money($x)) && Num::isZero(Num::money($y))) {
                    continue;
                }
                $rows[] = $line($this->categoryName($a[$id] ?? $b[$id]), $x, $y);
                $totals[$nature] = [Num::add($totals[$nature][0], $x), Num::add($totals[$nature][1], $y)];
            }
            $rows[] = $line(__('Total :name', ['name' => mb_strtolower(__($heading))]), $totals[$nature][0], $totals[$nature][1], true);
        }
        $rows[] = $line(__('Profit of the period'), Num::sub($totals['income'][0], $totals['expense'][0]), Num::sub($totals['income'][1], $totals['expense'][1]), true);

        return $rows;
    }

    /** Unusual activity: what the owner was told about. */
    public function alerts(Request $request): View
    {
        Gate::authorize('reports.view');
        $f = $request->validate(['from' => ['nullable', 'date'], 'to' => ['nullable', 'date']]);
        [$from, $to] = [$f['from'] ?? now()->subDays(30)->toDateString(), $f['to'] ?? today_iso()];
        $kind = $request->query('kind');
        $rows = \App\Models\Alert::with('user')->when(isset(\App\Services\Alerts::KINDS[$kind]), fn ($q) => $q->where('kind', $kind))
            ->whereDate('created_at', '>=', $from)->whereDate('created_at', '<=', $to)->orderByDesc('id')->limit(2000)->get()
            ->map(fn ($alert) => ['cells' => [fmt_date($alert->created_at).' '.$alert->created_at->timezone(config('app.timezone'))->format('H:i'), __(\App\Services\Alerts::KINDS[$alert->kind] ?? $alert->kind), $alert->text, (string) $alert->user?->name]])->all();

        return $this->sheet('Unusual Activity', 'triangle-exclamation', [['Date'], ['What'], ['Details'], ['User']], $rows, $from, $to,
            [['name' => 'kind', 'label' => 'What', 'options' => array_map(fn ($label) => __($label), \App\Services\Alerts::KINDS)]]);
    }

    /**
     * Cash forecast: the money of now in every currency, less the deposits that are to be paid
     * back and the recurring expenses of the days ahead. A deposit counts for what is still owed.
     */
    public function forecast(Request $request): View
    {
        Gate::authorize('reports.view');
        $days = max(1, min(365, (int) $request->query('days', 30)));
        $today = today_iso();
        $until = now()->addDays($days)->toDateString();

        $overdue = $due = $recurring = [];
        $left = [];   // what is still owed on each party account, so two papers never count the same money twice
        $vouchers = Voucher::with(['party.accounts.total'])->where('kind', Voucher::MONEY_IN)->whereNull('cancelled_at')->whereNotNull('due_date')
            ->whereDate('due_date', '<=', $until)->orderBy('due_date')->limit(5000)->get();
        foreach ($vouchers as $voucher) {
            $account = $voucher->party?->accounts->firstWhere('currency_id', $voucher->currency_id);
            if (! $account) {
                continue;
            }
            $left[$account->id] ??= Num::neg($account->total?->balance ?? 0);
            if (! Num::isPositive($left[$account->id])) {
                continue;
            }
            $amount = Num::cmp($voucher->amount, $left[$account->id]) === 1 ? $left[$account->id] : $voucher->amount;
            $left[$account->id] = Num::sub($left[$account->id], $amount);
            if ($voucher->due_date->toDateString() < $today) {
                $overdue[$voucher->currency_id] = Num::add($overdue[$voucher->currency_id] ?? 0, $amount);
            } else {
                $due[$voucher->currency_id] = Num::add($due[$voucher->currency_id] ?? 0, $amount);
            }
        }
        foreach (\App\Models\RecurringExpense::with('paidAccount')->where('is_active', true)->get() as $expense) {
            $times = 0;
            for ($day = Carbon::parse($today)->addDay(); $day->toDateString() <= $until; $day->addDay()) {
                $times += $day->day === $expense->day ? 1 : 0;
            }
            $currency = $expense->paidAccount->currency_id;
            $recurring[$currency] = Num::add($recurring[$currency] ?? 0, Num::mul($expense->amount, $times));
        }

        $rows = [];
        foreach (\App\Services\Days::totals(Branch::main()) as $id => $total) {
            $out = Num::add(Num::add($overdue[$id] ?? 0, $due[$id] ?? 0), $recurring[$id] ?? 0);
            if (Num::isZero(Num::money($total['balance'])) && Num::isZero(Num::money($out))) {
                continue;
            }
            $expected = Num::sub($total['balance'], $out);
            $rows[] = ['cells' => [$total['currency']->code, money($total['balance']), money($overdue[$id] ?? 0), money($due[$id] ?? 0), money($recurring[$id] ?? 0), money($expected),
                Num::isNegative(Num::money($expected)) ? __('Short by :amount', ['amount' => money(Num::abs($expected))]) : __('Enough')]];
        }

        return $this->sheet('Cash Forecast', 'chart-line', [['Currency'], ['Cash and bank now', 'end'], ['Paybacks overdue', 'end'], ['Paybacks falling due', 'end'], ['Recurring expenses', 'end'], ['Expected', 'end'], ['Status']], $rows, null, null,
            [['name' => 'days', 'label' => 'The days ahead', 'options' => [7 => __('7 days'), 30 => __('30 days'), 60 => __('60 days'), 90 => __('90 days')], 'value' => $days]]);
    }

    /** The lines of the profit and loss statement: headings, groups, totals and the profit of the period. */
    public function profitRows(string $from, string $to): array
    {
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

        return $rows;
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

        return $this->sheet('Balance Sheet', 'landmark', [['', ''], [Currency::base()->code, 'end']], $this->balanceRows($to), null, $to);
    }

    /** The lines of the balance sheet at the end of a date. */
    public function balanceRows(string $to): array
    {
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

        return $rows;
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
            ->selectRaw('c.id as id, c.name as name, c.name_local as name_local, c.nature as nature, SUM(l.base_debit) as debit, SUM(l.base_credit) as credit')->get();
    }

    private function categoryName(object $row): string
    {
        return (text_dir() === 'rtl' ? $row->name_local : null) ?: __($row->name);
    }
}
