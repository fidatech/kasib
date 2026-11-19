<?php

namespace App\Services;

use App\Exceptions\UserError;
use App\Models\Account;
use App\Models\AccountCategory;
use App\Models\Branch;
use App\Models\JournalLine;
use App\Models\Period;
use App\Support\Num;

/**
 * Which dates the books accept, and what the money of the business is today.
 *
 *  - An entry may be dated today or earlier, never in the future, and never on or before
 *    the day the last period was closed: a closed period is locked.
 *  - Money accounts (cash, bank, saraf and any category of that type) are added up per
 *    currency for the treasury, the rates page and the strip above the roznamcha.
 */
class Days
{
    /** Refuse an entry whose date the books do not accept. */
    public static function guard(Branch $branch, string $date): void
    {
        $date = substr($date, 0, 10);

        if ($date > today_iso()) {
            throw new UserError(__('An entry cannot be dated in the future.'));
        }

        // how many days back a paper may be dated; those who manage the settings are not held by it
        $days = (int) setting('backdate_days', '0');
        if ($days > 0 && $date < now()->subDays($days)->toDateString() && ! auth()->user()?->can('settings.manage')) {
            throw new UserError(__('A paper can be dated at most :days days back. Ask the owner to record an older one.', ['days' => $days]));
        }

        $closed = Period::max('closed_on');
        if ($closed && $date <= substr((string) $closed, 0, 10)) {
            throw new UserError(__('This date is in a closed period. Closed periods are locked.'));
        }
    }

    /**
     * Money accounts of the branch per currency, now: [currency id => balance, rate, value in the base currency].
     * A currency with money but no rate yet counts with the value it has in the books.
     */
    public static function totals(Branch $branch): array
    {
        $out = [];
        foreach (self::moneyAccounts($branch) as $account) {
            $id = $account->currency_id;
            $out[$id] ??= ['currency' => $account->currency, 'balance' => '0', 'booked' => '0'];
            $out[$id]['balance'] = Num::add($out[$id]['balance'], $account->total?->balance ?? 0);
            $out[$id]['booked'] = Num::add($out[$id]['booked'], $account->total?->base_balance ?? 0);
        }

        foreach ($out as $id => $row) {
            $rate = Rates::current($branch, $row['currency']);
            $out[$id]['balance'] = Num::money($row['balance']);
            $out[$id]['rate'] = $rate;
            $out[$id]['base'] = $rate === null ? Num::money($row['booked']) : Rates::toBase($row['balance'], $row['currency'], $branch);
        }

        return $out;
    }

    /**
     * The strip above the roznamcha of one day: per currency what the day started with,
     * what came in, what went out and what was there at its end.
     */
    public static function summary(Branch $branch, string $date): array
    {
        $accounts = self::moneyAccounts($branch);
        $moves = JournalLine::whereIn('account_id', $accounts->pluck('id'))
            ->where('is_void', false)->whereDayOf('date', $date)
            ->groupBy('currency_id')
            ->selectRaw('currency_id, COALESCE(SUM(debit),0) as debit, COALESCE(SUM(credit),0) as credit')
            ->toBase()->get()->keyBy('currency_id');

        $rows = [];
        foreach ($accounts->groupBy('currency_id') as $currencyId => $group) {
            $currency = $group->first()->currency;
            $in = Num::money($moves[$currencyId]->debit ?? 0);
            $out = Num::money($moves[$currencyId]->credit ?? 0);
            $opening = Num::money($group->reduce(fn ($sum, Account $a) => Num::add($sum, Ledger::balanceOn($a->id, $date, true)), '0'));
            $rows[] = [
                'currency' => $currency,
                'opening' => $opening,
                'in' => $in,
                'out' => $out,
                'closing' => Num::money(Num::sub(Num::add($opening, $in), $out)),
                'rate' => Rates::current($branch, $currency),
            ];
        }

        return ['day' => null, 'rows' => $rows];
    }

    /**
     * New rates change what foreign money is worth. Every asset and liability account kept in a
     * foreign currency (cash, bank, saraf, customers, suppliers ...) is valued again at the rate
     * of now; the difference from its value in the books is a gain or a loss and goes to
     * "Income from currency exchange". Money the business only holds for others gains on the
     * cash and loses the same on the debt, so it adds nothing. Returns the net gain (or loss).
     */
    public static function revalue(Branch $branch): string
    {
        $accounts = Account::with(['currency', 'total', 'category'])
            ->where('branch_id', $branch->id)
            ->whereHas('category', fn ($q) => $q->whereIn('nature', ['asset', 'liability']))
            ->whereHas('currency', fn ($q) => $q->where('is_base', false))
            ->orderBy('id')->get();

        $lines = [];
        $net = '0';
        foreach ($accounts as $account) {
            $balance = $account->total?->balance ?? 0;
            $booked = $account->total?->base_balance ?? 0;
            if ((Num::isZero($balance) && Num::isZero($booked)) || Rates::current($branch, $account->currency) === null) {
                continue;
            }
            $worth = Num::isNegative($balance)
                ? Num::neg(Rates::toBase(Num::abs($balance), $account->currency, $branch))
                : Rates::toBase($balance, $account->currency, $branch);
            $difference = Num::money(Num::sub($worth, $booked));
            if (Num::isZero($difference)) {
                continue;
            }
            $lines[] = ['account' => $account, 'side' => Num::isPositive($difference) ? 'debit' : 'credit', 'base' => Num::abs($difference)];
            $net = Num::add($net, $difference);
        }
        if (! $lines) {
            return '0.00';
        }

        $net = Num::money($net);
        $income = Accounts::system($branch, 'exchange_income');
        if (! Num::isZero($net)) {
            $lines[] = Num::isPositive($net) ? ['account' => $income, 'credit' => $net] : ['account' => $income, 'debit' => Num::abs($net)];
        }
        if (count($lines) < 2) {
            return '0.00';
        }
        Ledger::post($branch, 'revaluation', today_iso(), __('Value of foreign currencies at the new rates'), $lines);

        return $net;
    }

    /** The money accounts of a branch (cash, bank, saraf ...), with their running balances. */
    private static function moneyAccounts(Branch $branch)
    {
        return Account::with(['currency', 'total'])
            ->where('branch_id', $branch->id)
            ->whereIn('account_category_id', AccountCategory::moneyIds())
            ->orderBy('currency_id')->orderBy('id')
            ->get();
    }
}
