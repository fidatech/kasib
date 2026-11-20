<?php

namespace App\Services;

use App\Exceptions\UserError;
use App\Models\Account;
use App\Models\Branch;
use App\Models\Currency;
use App\Models\Period;
use App\Models\Setting;
use App\Models\User;
use App\Support\Num;
use Illuminate\Support\Facades\DB;

/**
 * Closing a period (a year, or after the reports are cleared with the bank), for the whole
 * business at once. In every branch the income and expense accounts are emptied into
 * "profit or loss of previous years"; for each branch the business says whether that profit
 * stays there or is added to capital. Nothing is deleted: the old period stays to be read,
 * balances carry on.
 */
class Closing
{
    /** Income and expense accounts of a branch that still hold something, and the profit they add up to. */
    public static function open(Branch $branch): array
    {
        $accounts = Account::with(['total', 'currency', 'category'])->where('branch_id', $branch->id)
            ->whereHas('category', fn ($q) => $q->whereIn('nature', ['income', 'expense']))->get()
            ->filter(fn (Account $a) => ! Num::isZero($a->total?->base_balance ?? '0') || ! Num::isZero($a->total?->balance ?? '0'))->values();

        return ['accounts' => $accounts, 'profit' => Num::money(Num::neg(Num::sum($accounts->map(fn (Account $a) => $a->total->base_balance))))];
    }

    /** @param  array<int|string, string>  $choices  branch id => "retained" or "capital" */
    public static function close(array $choices, User $user): Period
    {
        return DB::transaction(function () use ($choices, $user) {
            $number = (int) setting('period', '1');
            $afn = Currency::base();
            $summary = [];

            foreach (Branch::withBooks()->orderBy('id')->get() as $branch) {
                $to = ($choices[$branch->id] ?? 'retained') === 'capital' ? 'capital' : 'retained';
                ['accounts' => $accounts, 'profit' => $profit] = self::open($branch);
                $summary[] = ['branch_id' => $branch->id, 'name' => $branch->name, 'profit' => $profit, 'to' => $to];
                if ($accounts->isEmpty()) {
                    continue;
                }
                $retained = Accounts::system($branch, 'retained');

                $lines = [];
                foreach ($accounts as $account) {
                    $own = $account->total->balance;
                    $base = $account->total->base_balance;
                    $debit = Num::isNegative($base) || (Num::isZero($base) && Num::isNegative($own));
                    $lines[] = ['account' => $account, $debit ? 'debit' : 'credit' => Num::abs($own), 'base' => Num::abs($base), 'side' => $debit ? 'debit' : 'credit'];
                }
                if (! Num::isZero($profit)) {
                    $lines[] = ['account' => $retained, Num::isPositive($profit) ? 'credit' : 'debit' => Num::abs($profit)];
                }
                Ledger::post($branch, 'closing', today_iso(), __('Closing of period :number', ['number' => $number]), $lines);

                // profit added to capital: the partners' capital in the main branch, the representative's own funds in a branch
                if ($to === 'capital' && Num::isPositive($profit)) {
                    $capital = Accounts::open($branch, 'capital_from_profit', 'capital', 'Profit added to capital');
                    Ledger::post($branch, 'closing_transfer', today_iso(), __('Profit of period :number added to capital', ['number' => $number]), [
                        ['account' => $retained, 'debit' => $profit],
                        ['account' => $capital, 'credit' => $profit],
                    ]);
                }
            }

            $last = Period::orderByDesc('number')->first();
            $period = Period::create([
                'number' => $number, 'started_on' => $last?->closed_on, 'closed_on' => today_iso(), 'branches' => $summary,
                'closed_by' => $user->id,
            ]);

            Setting::updateOrCreate(['key' => 'period'], ['value' => (string) ($number + 1)]);
            app()->forgetInstance('erp.settings');
            audit('closed', null, 'Period '.$number);

            return $period;
        });
    }
}
