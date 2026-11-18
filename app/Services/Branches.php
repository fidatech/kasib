<?php

namespace App\Services;

use App\Exceptions\UserError;
use App\Models\Account;
use App\Models\Branch;
use App\Models\BranchTransfer;
use App\Models\ExchangeRate;
use App\Support\Num;
use Illuminate\Support\Facades\DB;

/** What happens between branches: a new branch is made ready, and money is sent from one to another. */
class Branches
{
    /** A new branch starts with the rates of the main branch and a cash account in every currency. */
    public static function prepare(Branch $branch): void
    {
        $main = Branch::main();
        foreach (ExchangeRate::where('branch_id', $main->id)->orderBy('id')->get()->keyBy('currency_id') as $rate) {
            if (! ExchangeRate::where('branch_id', $branch->id)->where('currency_id', $rate->currency_id)->exists()) {
                ExchangeRate::create(['branch_id' => $branch->id, 'currency_id' => $rate->currency_id, 'rate' => $rate->rate, 'buy_rate' => $rate->buy_rate, 'sell_rate' => $rate->sell_rate, 'created_by' => auth()->id()]);
            }
        }
        Accounts::ensureCash($branch);
    }

    /**
     * Money leaves a cash, bank or saraf account of one branch and arrives in one of another, in
     * the same currency. Each branch writes its side: the sender is owed by the receiver.
     */
    public static function transfer(Account $from, Account $to, string $date, mixed $amount, ?string $note = null): BranchTransfer
    {
        $amount = Num::money($amount);
        if (! Num::isPositive($amount)) {
            throw new UserError(__('The amount must be more than zero.'));
        }
        if ((int) $from->branch_id === (int) $to->branch_id) {
            throw new UserError(__('Both accounts are in the same branch. Use a transfer.'));
        }
        if ((int) $from->currency_id !== (int) $to->currency_id) {
            throw new UserError(__('Money goes to another branch in the same currency. Exchange it first, or at the other branch.'));
        }

        return DB::transaction(function () use ($from, $to, $date, $amount, $note) {
            $sender = Branch::findOrFail($from->branch_id);
            $receiver = Branch::findOrFail($to->branch_id);
            $currency = $from->currency;
            $transfer = BranchTransfer::create([
                'number' => Sequence::next('BT'), 'date' => $date, 'from_branch_id' => $sender->id, 'to_branch_id' => $receiver->id,
                'from_account_id' => $from->id, 'to_account_id' => $to->id, 'currency_id' => $currency->id, 'amount' => $amount, 'note' => $note, 'created_by' => auth()->id(),
            ]);
            // one value in the base currency for both sides, so the two settlement accounts cancel out exactly
            $base = Rates::toBase($amount, $currency, $sender);
            $text = trim($transfer->number.' · '.$sender->name.' → '.$receiver->name.($note ? ' · '.$note : ''));
            Ledger::post($sender, 'branch_transfer', $date, $text, [
                ['account' => self::settlement($sender, $receiver, $currency), 'debit' => $amount, 'base' => $base],
                ['account' => $from, 'credit' => $amount, 'base' => $base],
            ], $transfer);
            Ledger::post($receiver, 'branch_transfer', $date, $text, [
                ['account' => $to, 'debit' => $amount, 'base' => $base],
                ['account' => self::settlement($receiver, $sender, $currency), 'credit' => $amount, 'base' => $base],
            ], $transfer);
            audit('created', null, 'Branch transfer '.$transfer->number.' '.$amount.' '.$currency->code.' '.$sender->name.' → '.$receiver->name);
            Alerts::entry(fn () => __('Money to another branch').' '.$transfer->number.' · '.money($amount, $currency->code), $date, $base, $transfer);

            return $transfer;
        });
    }

    public static function cancel(BranchTransfer $transfer, ?string $reason = null): void
    {
        if ($transfer->isCancelled()) {
            return;
        }
        DB::transaction(function () use ($transfer, $reason) {
            $transfer->update(['cancelled_at' => now()]);
            Ledger::voidSource($transfer, $reason);
            audit('cancelled', null, 'Branch transfer '.$transfer->number);
        });
    }

    private static function settlement(Branch $book, Branch $other, \App\Models\Currency $currency): Account
    {
        return Accounts::interBranch($book, $other, $currency);
    }
}
