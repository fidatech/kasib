<?php

namespace App\Services;

use App\Exceptions\UserError;
use App\Models\Account;
use App\Models\Branch;
use App\Models\Distribution;
use App\Models\Shareholder;
use App\Support\Num;
use Illuminate\Support\Facades\DB;

/** Profit handed to the partners, each by his share. It is in the books of the main branch only. */
class DistributionService
{
    /** @return list<array{shareholder_id: int, name: string, percent: string, amount: string}> */
    public static function shares(mixed $amount): array
    {
        $holders = Shareholder::where('is_active', true)->where('share_percent', '>', 0)->orderBy('id')->get();
        $total = Num::sum($holders->pluck('share_percent'));
        if (! Num::isPositive($total)) {
            throw new UserError(__('Write the shares of the partners first.'));
        }
        $shares = [];
        $given = '0';
        foreach ($holders as $i => $holder) {
            // the last partner gets what is left, so the parts add up to the whole
            $part = $i === $holders->count() - 1 ? Num::money(Num::sub($amount, $given)) : Num::money(Num::div(Num::mul($amount, $holder->share_percent), $total));
            $given = Num::add($given, $part);
            $shares[] = ['shareholder_id' => $holder->id, 'name' => $holder->name, 'percent' => Num::format($holder->share_percent, 4, true), 'amount' => $part];
        }

        return $shares;
    }

    public static function record(mixed $amount, Account $paid, ?string $note = null): Distribution
    {
        $main = Branch::main();
        $amount = Num::money($amount);
        if (! Num::isPositive($amount)) {
            throw new UserError(__('The amount must be more than zero.'));
        }
        if ((int) $paid->branch_id !== (int) $main->id || ! $paid->currency->is_base) {
            throw new UserError(__('Profit is paid from an AFN account of the main branch.'));
        }

        return DB::transaction(function () use ($main, $amount, $paid, $note) {
            $distribution = Distribution::create(['number' => Sequence::next('PD'), 'date' => today_iso(), 'amount' => $amount, 'paid_account_id' => $paid->id, 'shares' => self::shares($amount), 'note' => $note, 'created_by' => auth()->id()]);
            Ledger::post($main, 'distribution', today_iso(), __('Profit paid to partners').' '.$distribution->number, [
                ['account' => Accounts::system($main, 'retained'), 'debit' => $amount],
                ['account' => $paid, 'credit' => $amount],
            ], $distribution);
            audit('created', $distribution, 'Profit paid to partners '.$amount);

            return $distribution;
        });
    }

    public static function cancel(Distribution $distribution, ?string $reason = null): void
    {
        if ($distribution->isCancelled()) {
            return;
        }
        DB::transaction(function () use ($distribution, $reason) {
            $distribution->update(['cancelled_at' => now()]);
            Ledger::voidSource($distribution, $reason);
        });
    }
}
