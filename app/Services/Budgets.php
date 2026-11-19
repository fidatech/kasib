<?php

namespace App\Services;

use App\Models\AccountCategory;
use App\Models\Budget;
use App\Support\Num;
use App\Support\Shamsi;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/** Monthly limits of the expense categories. The month is the one of the calendar of the business. */
class Budgets
{
    /** @return array{0: string, 1: string} the first and the last day of the month of today */
    public static function month(): array
    {
        $today = Carbon::parse(today_iso());
        if (calendar() !== 'shamsi') {
            return [$today->copy()->startOfMonth()->toDateString(), $today->copy()->endOfMonth()->toDateString()];
        }
        [$y, $m] = Shamsi::fromGregorian($today->year, $today->month, $today->day);
        $first = fn (int $y, int $m) => vsprintf('%04d-%02d-%02d', Shamsi::toGregorian($y, $m, 1));
        $next = $m === 12 ? $first($y + 1, 1) : $first($y, $m + 1);

        return [$first($y, $m), Carbon::parse($next)->subDay()->toDateString()];
    }

    /** What the category cost between two dates, in the base currency. */
    public static function spent(int $categoryId, string $from, string $to): string
    {
        $row = DB::table('journal_lines as l')->join('accounts as a', 'a.id', '=', 'l.account_id')
            ->where('a.account_category_id', $categoryId)->where('l.is_void', false)->whereIn('l.branch_id', \App\Models\Branch::reading())
            ->whereDayOf('l.date', '>=', $from)->whereDayOf('l.date', '<=', $to)
            ->selectRaw('COALESCE(SUM(l.base_debit),0) as debit, COALESCE(SUM(l.base_credit),0) as credit')->first();

        return Num::money(Num::sub($row->debit ?? 0, $row->credit ?? 0));
    }

    /** How much of the budget is used, in percent (whole numbers). */
    public static function percent(mixed $spent, mixed $budget): int
    {
        return Num::isPositive($budget) ? (int) floor((float) Num::div(Num::mul($spent, '100'), $budget)) : 0;
    }

    /** An expense of this category was recorded: warn once at 80% and once at 100% of the month. */
    public static function check(AccountCategory $category): void
    {
        $budget = Budget::where('account_category_id', $category->id)->first();
        if (! $budget) {
            return;
        }
        [$from, $to] = self::month();
        $spent = self::spent($category->id, $from, $to);
        $percent = self::percent($spent, $budget->amount);
        $level = $percent >= 100 ? 100 : ($percent >= 80 ? 80 : 0);
        $before = $budget->warned_month === $from ? $budget->warned_level : 0;
        if ($level > $before) {
            $budget->update(['warned_month' => $from, 'warned_level' => $level]);
            Alerts::raise('budget', $category->label().': '.money($spent).' / '.money($budget->amount).' ('.$percent.'%)', $budget);
        }
    }
}
