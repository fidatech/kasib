<?php

namespace App\Services;

use App\Models\AccountGroup;
use App\Models\Branch;
use App\Support\Num;
use Illuminate\Support\Facades\DB;

/** The figures of the dashboard: earned and spent, money in and out by day, who owes most and who is owed most. */
class Insights
{
    /** @return array{income: string, expense: string, profit: string} in the base currency */
    public static function result(string $from, string $to): array
    {
        $rows = DB::table('journal_lines as l')->join('journal_entries as e', 'e.id', '=', 'l.journal_entry_id')->join('accounts as a', 'a.id', '=', 'l.account_id')
            ->join('account_categories as c', 'c.id', '=', 'a.account_category_id')->where('l.is_void', false)->whereIn('c.nature', ['income', 'expense'])->where('e.type', '!=', 'closing')
            ->whereIn('l.branch_id', Branch::reading())->where('l.date', '>=', $from)->where('l.date', '<=', $to)
            ->groupBy('c.nature')->selectRaw('c.nature as nature, COALESCE(SUM(l.base_debit), 0) as debit, COALESCE(SUM(l.base_credit), 0) as credit')->get()->keyBy('nature');
        $income = Num::money(Num::sub($rows['income']->credit ?? 0, $rows['income']->debit ?? 0));
        $expense = Num::money(Num::sub($rows['expense']->debit ?? 0, $rows['expense']->credit ?? 0));

        return ['income' => $income, 'expense' => $expense, 'profit' => Num::money(Num::sub($income, $expense))];
    }

    /** Earned and spent in each of the last months, the oldest first. */
    public static function months(int $count = 6): array
    {
        $out = [];
        for ($back = $count - 1; $back >= 0; $back--) {
            $month = now()->startOfMonth()->subMonthsNoOverflow($back);
            $out[] = ['label' => fmt_date($month->toDateString()), 'month' => $month->format('Y-m')] + self::result($month->toDateString(), $month->copy()->endOfMonth()->toDateString());
        }

        return $out;
    }

    /** What came into the cash, bank and saraf accounts and what left them on each of the last days, valued in the base currency. */
    public static function flow(int $days = 14): array
    {
        $accounts = DB::table('accounts as a')->join('parties as p', 'p.id', '=', 'a.party_id')->join('account_groups as g', 'g.id', '=', 'p.account_group_id')
            ->where('g.type', AccountGroup::MONEY)->whereIn('a.branch_id', Branch::reading())->pluck('a.id');
        $from = now()->subDays($days - 1)->toDateString();
        $rows = DB::table('journal_lines')->whereIn('account_id', $accounts)->where('is_void', false)->where('date', '>=', $from)
            ->groupBy('date')->selectRaw('date, COALESCE(SUM(base_debit), 0) as came, COALESCE(SUM(base_credit), 0) as went')->get()->keyBy(fn ($row) => substr((string) $row->date, 0, 10));
        $out = [];
        for ($back = $days - 1; $back >= 0; $back--) {
            $date = now()->subDays($back)->toDateString();
            $out[] = ['date' => $date, 'in' => Num::money($rows[$date]->came ?? 0), 'out' => Num::money($rows[$date]->went ?? 0)];
        }

        return $out;
    }

    /**
     * Customers and suppliers by what stands between them and the business, in the base currency.
     *
     * @return array{owe: array, owed: array, owe_total: string, owed_total: string, owe_count: int, owed_count: int}
     */
    public static function parties(int $top = 6): array
    {
        $rows = DB::table('account_balances as b')->join('accounts as a', 'a.id', '=', 'b.account_id')->join('parties as p', 'p.id', '=', 'a.party_id')
            ->join('account_groups as g', 'g.id', '=', 'p.account_group_id')->where('g.type', AccountGroup::PARTY)->whereIn('a.branch_id', Branch::reading())
            ->groupBy('p.id', 'p.name', 'g.key')->selectRaw('p.id as id, p.name as name, g.key as section, SUM(b.base_balance) as balance')->get()
            ->map(fn ($row) => ['id' => $row->id, 'name' => $row->name, 'section' => $row->section, 'amount' => Num::money($row->balance)]);
        $owe = $rows->filter(fn ($row) => Num::isPositive($row['amount']))->sortByDesc(fn ($row) => (float) $row['amount'])->values();
        $owed = $rows->filter(fn ($row) => Num::isNegative($row['amount']))->sortBy(fn ($row) => (float) $row['amount'])->values()->map(fn ($row) => ['amount' => Num::abs($row['amount'])] + $row);

        return ['owe' => $owe->take($top)->all(), 'owed' => $owed->take($top)->all(), 'owe_total' => Num::money(Num::sum($owe->pluck('amount'))), 'owed_total' => Num::money(Num::sum($owed->pluck('amount'))),
            'owe_count' => $owe->count(), 'owed_count' => $owed->count()];
    }

    /** The change from one figure to another, in per cent; null when there is nothing to compare with. */
    public static function change(mixed $now, mixed $before): ?float
    {
        return Num::isZero(Num::money($before)) ? null : round(((float) $now - (float) $before) / abs((float) $before) * 100, 1);
    }
}
