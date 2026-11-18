<?php

namespace App\Services;

use App\Exceptions\UserError;
use App\Models\Account;
use App\Models\Branch;
use App\Models\CapitalEntry;
use App\Models\Shareholder;
use App\Support\Num;
use Illuminate\Support\Facades\DB;

/**
 * Capital the partners pay in and take out (drawings).
 * Both are in AFN and both are in the books of the main branch.
 */
class CapitalService
{
    public static function record(Shareholder $shareholder, string $kind, string $date, mixed $amount, ?Account $money = null, ?Branch $for = null, ?string $notes = null): CapitalEntry
    {
        $amount = Num::money($amount);
        if (! Num::isPositive($amount)) {
            throw new UserError(__('The amount must be more than zero.'));
        }

        return DB::transaction(function () use ($shareholder, $kind, $date, $amount, $money, $for, $notes) {
            $main = Branch::main();
            $capital = Accounts::shareholder($main, $shareholder);

            if (! $money || ! $money->currency->is_base) {
                throw new UserError(__('Choose a cash or bank account in the base currency.'));
            }
            if ($kind === 'withdrawal') {
                if (Num::cmp($amount, Num::neg(Ledger::balance($capital))) === 1) {
                    throw new UserError(__(':name does not have that much capital.', ['name' => $shareholder->name]));
                }
                $lines = [['account' => $capital, 'debit' => $amount], ['account' => $money, 'credit' => $amount]];
            } else {
                $lines = [['account' => $money, 'debit' => $amount], ['account' => $capital, 'credit' => $amount]];
            }

            $entry = CapitalEntry::create([
                'shareholder_id' => $shareholder->id,
                'kind' => $kind,
                'branch_id' => null,
                'date' => $date,
                'amount' => $amount,
                'money_account_id' => $money?->id,
                'notes' => $notes,
                'created_by' => auth()->id(),
            ]);

            Ledger::post($main, 'capital', $date, trim(__(CapitalEntry::KINDS[$kind]).': '.$shareholder->name.($for ? ' ('.$for->name.')' : '')), $lines, $entry);
            audit('created', $entry, CapitalEntry::KINDS[$kind].' '.$shareholder->name.' '.$amount);

            return $entry;
        });
    }

    public static function cancel(CapitalEntry $entry, ?string $reason = null): void
    {
        if ($entry->isCancelled()) {
            return;
        }

        DB::transaction(function () use ($entry, $reason) {
            $entry->update(['cancelled_at' => now()]);
            Ledger::voidSource($entry, $reason);
        });
    }

    /** What a partner has in the business: capital paid in, less what was taken out. */
    public static function balance(Shareholder $shareholder): string
    {
        return Num::money(Num::neg(Ledger::balance(Accounts::shareholder(Branch::main(), $shareholder))));
    }
}
