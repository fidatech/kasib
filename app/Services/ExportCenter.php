<?php

namespace App\Services;

use App\Http\Controllers\ReportController;
use App\Models\Account;
use App\Models\AccountGroup;
use App\Models\Branch;
use App\Models\CapitalEntry;
use App\Models\CashCount;
use App\Models\Currency;
use App\Models\Deal;
use App\Models\Expense;
use App\Models\JournalLine;
use App\Models\Party;
use App\Models\Voucher;
use App\Support\Num;
use App\Support\Xlsx;

/**
 * The Export Center: everything a business recorded in a period, as one Excel workbook with a
 * summary and one sheet for every module that was ticked. Amounts are written as numbers, so
 * they can be added up and filtered in Excel.
 */
class ExportCenter
{
    /** key => name of the sheet */
    public const MODULES = [
        'accounts' => 'Accounts',
        'money_in' => 'Money In',
        'money_out' => 'Money Out',
        'transfers' => 'Transfers',
        'exchanges' => 'Exchanges',
        'income' => 'Income',
        'expenses' => 'Expenses',
        'deals' => 'Deals',
        'cash_counts' => 'Daily Cash Count',
        'capital' => 'Capital & Drawings',
        'journal' => 'Roznamcha',
        'trial' => 'Trial Balance',
        'profit' => 'Profit & Loss',
        'balance' => 'Balance Sheet',
    ];

    /** No sheet gets more rows than this; the summary says when a sheet was cut. */
    private const LIMIT = 50000;

    /** @param  array<int, string>  $modules  keys of MODULES */
    public static function build(string $from, string $to, array $modules): string
    {
        $sheets = [];
        $counts = [];
        foreach (array_keys(self::MODULES) as $key) {
            if (! in_array($key, $modules, true)) {
                continue;
            }
            $rows = self::{'sheet'.str_replace('_', '', ucwords($key, '_'))}($from, $to);
            $counts[$key] = max(0, count($rows) - 1);
            $sheets[__(self::MODULES[$key])] = $rows;
        }

        return Xlsx::workbook([__('Summary') => self::summary($from, $to, $counts)] + $sheets, text_dir() === 'rtl');
    }

    private static function summary(string $from, string $to, array $counts): array
    {
        $base = Currency::base()->code;
        $rows = [
            self::head([(string) (setting('company_name') ?: tenant()?->name), '']),
            self::row([__('From'), fmt_date($from)]),
            self::row([__('To'), fmt_date($to)]),
            self::row([__('Made on'), fmt_date(today_iso()).' '.now()->format('H:i')]),
            self::row([__('Made by'), (string) auth()->user()?->name]),
            self::row(['', '']),
            self::head([__('Sheet'), __('Rows')]),
        ];
        foreach ($counts as $key => $count) {
            $rows[] = self::row([__(self::MODULES[$key]).($count >= self::LIMIT ? ' ('.__('first :count rows only', ['count' => number_format(self::LIMIT)]).')' : ''), self::n($count)]);
        }
        $rows[] = self::row(['', '']);
        $rows[] = self::head([__('Cash, bank and saraf'), __('Balance'), __('Value in :currency', ['currency' => $base])]);
        foreach (Days::totals(current_branch()) as $total) {
            $rows[] = self::row([$total['currency']->code, self::n($total['balance']), self::n($total['base'])]);
        }

        return $rows;
    }

    private static function sheetAccounts(string $from, string $to): array
    {
        $rows = [self::head([__('Category'), __('Type'), __('Name'), __('Phone'), __('Address'), __('Currency'), __('Balance'), __('In use')])];
        $groups = AccountGroup::orderBy('sort')->pluck('id');
        foreach (Party::with(['group', 'accounts.total', 'accounts.currency'])->whereIn('account_group_id', $groups)->orderBy('account_group_id')->orderBy('name')->limit(self::LIMIT)->get() as $party) {
            $balances = Vouchers::balances($party) ?: [['currency' => null, 'amount' => '0.00']];
            foreach ($balances as $balance) {
                // a party: above zero the business owes it, below zero it owes the business
                $rows[] = self::row([$party->group->label(), __(AccountGroup::TYPES[$party->group->type] ?? $party->group->type), $party->name, (string) $party->phone, (string) $party->address,
                    (string) $balance['currency']?->code, self::n($balance['amount']), $party->is_active ? __('Yes') : __('No')]);
            }
        }

        return $rows;
    }

    private static function vouchers(string $kind, string $from, string $to)
    {
        return Voucher::with(['party', 'account', 'otherAccount.category', 'currency', 'otherCurrency', 'creator'])->where('kind', $kind)
            ->whereDayOf('date', '>=', $from)->whereDayOf('date', '<=', $to)->orderBy('date')->orderBy('id')->limit(self::LIMIT)->get();
    }

    private static function state(Voucher|Expense|CashCount|CapitalEntry $paper): string
    {
        return $paper->isCancelled() ? __('Cancelled') : '';
    }

    private static function sheetMoneyIn(string $from, string $to): array
    {
        $rows = [self::head([__('No.'), __('Date'), __('Account'), __('Amount'), __('Currency'), __('Received into'), __('To be paid back on'), __('Details'), __('User'), __('Status')])];
        foreach (self::vouchers(Voucher::MONEY_IN, $from, $to) as $v) {
            $rows[] = self::row([$v->number, fmt_date($v->date), (string) $v->party?->name, self::n($v->amount), $v->currency->code, $v->account->label(), fmt_date($v->due_date), (string) $v->description, (string) $v->creator?->name, self::state($v)]);
        }

        return $rows;
    }

    private static function sheetMoneyOut(string $from, string $to): array
    {
        $rows = [self::head([__('No.'), __('Date'), __('Account'), __('Amount'), __('Currency'), __('Paid from'), __('Details'), __('User'), __('Status')])];
        foreach (self::vouchers(Voucher::MONEY_OUT, $from, $to) as $v) {
            $rows[] = self::row([$v->number, fmt_date($v->date), (string) $v->party?->name, self::n($v->amount), $v->currency->code, $v->account->label(), (string) $v->description, (string) $v->creator?->name, self::state($v)]);
        }

        return $rows;
    }

    private static function sheetTransfers(string $from, string $to): array
    {
        $rows = [self::head([__('No.'), __('Date'), __('From'), __('To'), __('Amount'), __('Fee'), __('Currency'), __('Details'), __('User'), __('Status')])];
        foreach (self::vouchers(Voucher::TRANSFER, $from, $to) as $v) {
            $rows[] = self::row([$v->number, fmt_date($v->date), $v->account->label(), $v->otherAccount->label(), self::n($v->amount), self::n($v->fee), $v->currency->code, (string) $v->description, (string) $v->creator?->name, self::state($v)]);
        }

        return $rows;
    }

    private static function sheetExchanges(string $from, string $to): array
    {
        $base = Currency::base()->code;
        $rows = [self::head([__('No.'), __('Date'), __('Given'), __('Currency'), __('From'), __('Received'), __('Currency'), __('Into'), __('Deal rate'), __('Profit').' '.$base, __('Details'), __('User'), __('Status')])];
        foreach (self::vouchers(Voucher::EXCHANGE, $from, $to) as $v) {
            $rows[] = self::row([$v->number, fmt_date($v->date), self::n($v->amount), $v->currency->code, $v->account->label(), self::n($v->other_amount), (string) $v->otherCurrency?->code, $v->otherAccount->label(),
                ['n' => Num::round($v->rate, 6)], self::n($v->profit), (string) $v->description, (string) $v->creator?->name, self::state($v)]);
        }

        return $rows;
    }

    private static function sheetIncome(string $from, string $to): array
    {
        $base = Currency::base()->code;
        $rows = [self::head([__('No.'), __('Date'), __('Income category'), __('Amount'), __('Currency'), __('Value in :currency', ['currency' => $base]), __('Received into'), __('Details'), __('User'), __('Status')])];
        foreach (self::vouchers(Voucher::INCOME, $from, $to) as $v) {
            $rows[] = self::row([$v->number, fmt_date($v->date), $v->otherAccount->category->label(), self::n($v->amount), $v->currency->code, self::n($v->base_amount), $v->account->label(), (string) $v->description, (string) $v->creator?->name, self::state($v)]);
        }

        return $rows;
    }

    private static function sheetExpenses(string $from, string $to): array
    {
        $base = Currency::base()->code;
        $rows = [self::head([__('Date'), __('Expense category'), __('Amount'), __('Currency'), __('Value in :currency', ['currency' => $base]), __('Paid from'), __('Details'), __('User'), __('Status')])];
        $expenses = Expense::with(['category', 'paidAccount', 'currency', 'creator'])->whereDayOf('date', '>=', $from)->whereDayOf('date', '<=', $to)->orderBy('date')->orderBy('id')->limit(self::LIMIT)->get();
        foreach ($expenses as $e) {
            $rows[] = self::row([fmt_date($e->date), $e->category->label(), self::n($e->amount), $e->currency->code, self::n($e->base_amount), $e->paidAccount->label(), (string) $e->description, (string) $e->creator?->name, self::state($e)]);
        }

        return $rows;
    }

    private static function sheetDeals(string $from, string $to): array
    {
        $base = Currency::base()->code;
        $rows = [self::head([__('No.'), __('Deal'), __('Opened on'), __('Status'), __('Closed on'), __('Cost').' '.$base, __('Received').' '.$base, __('Profit or loss').' '.$base])];
        foreach (Deal::whereDayOf('opened_on', '<=', $to)->where(fn ($q) => $q->whereNull('closed_on')->orWhereDate('closed_on', '>=', $from))->orderBy('id')->limit(self::LIMIT)->get() as $deal) {
            $f = Deals::figures($deal);
            $rows[] = self::row([$deal->number, $deal->name, fmt_date($deal->opened_on), $deal->isOpen() ? __('Open') : __('Closed'), fmt_date($deal->closed_on), self::n($f['cost']), self::n($f['received']), self::n($deal->isOpen() ? $f['result'] : $deal->result)]);
        }

        return $rows;
    }

    private static function sheetCashCounts(string $from, string $to): array
    {
        $rows = [self::head([__('No.'), __('Date'), __('Account'), __('Currency'), __('In the books'), __('Counted'), __('Difference'), __('Notes'), __('User'), __('Status')])];
        foreach (CashCount::with(['account', 'currency', 'creator'])->whereDayOf('date', '>=', $from)->whereDayOf('date', '<=', $to)->orderBy('date')->orderBy('id')->limit(self::LIMIT)->get() as $c) {
            $rows[] = self::row([$c->number, fmt_date($c->date), $c->account->label(), $c->currency->code, self::n($c->book), self::n($c->counted), self::n($c->difference), (string) $c->note, (string) $c->creator?->name, self::state($c)]);
        }

        return $rows;
    }

    private static function sheetCapital(string $from, string $to): array
    {
        $rows = [self::head([__('Date'), __('Shareholder'), __('Kind'), __('Amount'), __('Notes'), __('Status')])];
        foreach (CapitalEntry::with('shareholder')->whereDayOf('date', '>=', $from)->whereDayOf('date', '<=', $to)->orderBy('date')->orderBy('id')->limit(self::LIMIT)->get() as $e) {
            $rows[] = self::row([fmt_date($e->date), (string) $e->shareholder?->name, __(CapitalEntry::KINDS[$e->kind] ?? $e->kind), self::n($e->amount), (string) $e->notes, self::state($e)]);
        }

        return $rows;
    }

    private static function sheetJournal(string $from, string $to): array
    {
        $base = Currency::base()->code;
        $rows = [self::head([__('Date'), __('No.'), __('Transaction type'), __('Details'), __('Account'), __('Currency'), __('Debit'), __('Credit'), __('Debit').' '.$base, __('Credit').' '.$base, __('User')])];
        $lines = JournalLine::with(['entry.creator', 'account.currency'])->where('is_void', false)
            ->whereDayOf('date', '>=', $from)->whereDayOf('date', '<=', $to)->orderBy('date')->orderBy('id')->limit(self::LIMIT)->get();
        foreach ($lines as $line) {
            $rows[] = self::row([fmt_date($line->date), $line->entry->number, $line->entry->type, (string) ($line->memo ?: $line->entry->description), $line->account->label(), $line->account->currency->code,
                self::n($line->debit), self::n($line->credit), self::n($line->base_debit), self::n($line->base_credit), (string) $line->entry->creator?->name]);
        }

        return $rows;
    }

    /** Every account with a balance on the last day of the period. */
    private static function sheetTrial(string $from, string $to): array
    {
        $base = Currency::base()->code;
        $rows = [self::head([__('Account'), __('Group'), __('Currency'), __('Balance'), __('Debit').' '.$base, __('Credit').' '.$base])];
        $sums = JournalLine::where('is_void', false)->whereDayOf('date', '<=', $to)->groupBy('account_id')
            ->selectRaw('account_id, SUM(debit) as debit, SUM(credit) as credit, SUM(base_debit) as base_debit, SUM(base_credit) as base_credit')->toBase()->get()->keyBy('account_id');
        $debit = '0';
        $credit = '0';
        foreach (Account::with(['category', 'currency'])->whereIn('id', $sums->keys())->orderBy('account_category_id')->orderBy('name')->get() as $account) {
            $sum = $sums[$account->id];
            $own = Num::money(Num::sub($sum->debit, $sum->credit));
            $value = Num::money(Num::sub($sum->base_debit, $sum->base_credit));
            if (Num::isZero($own) && Num::isZero($value)) {
                continue;
            }
            $isDebit = ! Num::isNegative($value);
            $rows[] = self::row([$account->label(), $account->category->label(), $account->currency->code, self::n($own), $isDebit ? self::n($value) : '', $isDebit ? '' : self::n(Num::abs($value))]);
            $debit = Num::add($debit, $isDebit ? $value : '0');
            $credit = Num::add($credit, $isDebit ? '0' : Num::abs($value));
        }
        $rows[] = self::head([__('Total'), '', '', '', self::n($debit), self::n($credit)]);

        return $rows;
    }

    private static function sheetProfit(string $from, string $to): array
    {
        return self::statement(app(ReportController::class)->profitRows($from, $to));
    }

    private static function sheetBalance(string $from, string $to): array
    {
        return self::statement(app(ReportController::class)->balanceRows($to));
    }

    /** The lines of a statement of the reports page, with its amounts turned back into numbers. */
    private static function statement(array $lines): array
    {
        $rows = [self::head(['', Currency::base()->code])];
        foreach ($lines as $line) {
            if (isset($line['heading'])) {
                $rows[] = self::head([$line['heading'], '']);

                continue;
            }
            $cells = [$line['cells'][0], self::n(str_replace(',', '', (string) $line['cells'][1]))];
            $rows[] = empty($line['total']) ? self::row($cells) : self::head($cells);
        }

        return $rows;
    }

    private static function head(array $cells): array
    {
        return ['bold' => true, 'cells' => $cells];
    }

    private static function row(array $cells): array
    {
        return ['cells' => $cells];
    }

    /** A number cell. */
    private static function n(mixed $value): array
    {
        return ['n' => is_int($value) ? (string) $value : Num::money($value ?: 0)];
    }
}
