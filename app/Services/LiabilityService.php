<?php

namespace App\Services;

use App\Exceptions\UserError;
use App\Models\Account;
use App\Models\LiabilityEntry;
use App\Support\Num;
use Illuminate\Support\Facades\DB;

/** What a branch owes: money received that must be given back, and the payments of it. */
class LiabilityService
{
    public static function record(Account $liability, string $kind, string $date, mixed $amount, Account $money, ?string $description = null): LiabilityEntry
    {
        $amount = Num::money($amount);
        if (! Num::isPositive($amount)) {
            throw new UserError(__('The amount must be more than zero.'));
        }
        if ((int) $liability->branch_id !== (int) $money->branch_id) {
            throw new UserError(__('The account :name belongs to another branch.', ['name' => $money->name]));
        }
        if ((int) $liability->currency_id !== (int) $money->currency_id) {
            throw new UserError(__('The two accounts must be in the same currency.'));
        }

        return DB::transaction(function () use ($liability, $kind, $date, $amount, $money, $description) {
            if ($kind === 'payment' && Num::cmp($amount, Num::neg(Ledger::balance($liability))) === 1) {
                throw new UserError(__('That is more than is owed on :name.', ['name' => $liability->name]));
            }

            $entry = LiabilityEntry::create([
                'branch_id' => $liability->branch_id, 'account_id' => $liability->id, 'kind' => $kind, 'date' => $date,
                'money_account_id' => $money->id, 'amount' => $amount, 'description' => $description, 'created_by' => auth()->id(),
            ]);

            Ledger::post($liability->branch, 'liability', $date, trim(__(LiabilityEntry::KINDS[$kind]).': '.$liability->name.' '.$description), $kind === 'payment'
                ? [['account' => $liability, 'debit' => $amount], ['account' => $money, 'credit' => $amount]]
                : [['account' => $money, 'debit' => $amount], ['account' => $liability, 'credit' => $amount]], $entry);
            audit('created', $entry, LiabilityEntry::KINDS[$kind].' '.$liability->name.' '.$amount);

            return $entry;
        });
    }

    public static function cancel(LiabilityEntry $entry, ?string $reason = null): void
    {
        if ($entry->isCancelled()) {
            return;
        }

        DB::transaction(function () use ($entry, $reason) {
            $entry->update(['cancelled_at' => now()]);
            Ledger::voidSource($entry, $reason);
        });
    }
}
