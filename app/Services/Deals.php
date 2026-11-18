<?php

namespace App\Services;

use App\Exceptions\UserError;
use App\Models\Account;
use App\Models\AccountCategory;
use App\Models\Branch;
use App\Models\CashCount;
use App\Models\Deal;
use App\Models\Voucher;
use App\Support\Num;
use Illuminate\Support\Facades\DB;

/**
 * Deals and the daily cash count.
 *
 * A deal has an account of its own, in the base currency. What is paid for it goes in, what
 * comes from it goes out; when the deal is closed, what is left is its loss and what came in
 * above the cost is its profit. Nobody types the profit: it is worked out.
 */
class Deals
{
    public static function open(string $name, ?string $notes = null, ?string $date = null): Deal
    {
        return DB::transaction(function () use ($name, $notes, $date) {
            $deal = Deal::create([
                'number' => Sequence::next('DL'), 'name' => mb_substr($name, 0, 150), 'status' => 'open',
                'opened_on' => $date ?? today_iso(), 'notes' => $notes, 'created_by' => auth()->id(),
            ]);
            $account = Accounts::open(Branch::main(), 'deal.'.$deal->id, 'deal_goods', $deal->number.' '.$deal->name);
            $deal->update(['account_id' => $account->id]);
            audit('created', $deal, 'Deal '.$deal->number.' '.$deal->name);

            return $deal;
        });
    }

    /** What the deal cost so far, what came in from it, and what that leaves (all in the base currency). */
    public static function figures(Deal $deal): array
    {
        $papers = Voucher::where('deal_id', $deal->id)->whereNull('cancelled_at')->get(['kind', 'base_amount']);
        $cost = Num::sum($papers->where('kind', Voucher::DEAL_COST)->pluck('base_amount'));
        $received = Num::sum($papers->where('kind', Voucher::DEAL_RECEIPT)->pluck('base_amount'));

        return ['cost' => Num::money($cost), 'received' => Num::money($received), 'result' => Num::money(Num::sub($received, $cost))];
    }

    /**
     * Close a deal: what its account still holds is written off as its loss, and what came in
     * above the cost is booked as its profit.
     */
    public static function close(Deal $deal, ?string $date = null): Deal
    {
        if (! $deal->isOpen()) {
            throw new UserError(__('This deal is already closed.'));
        }

        return DB::transaction(function () use ($deal, $date) {
            $branch = Branch::main();
            $date ??= today_iso();
            $held = Ledger::baseBalance($deal->account_id);   // above zero: cost not covered yet
            if (Num::isPositive($held)) {
                Ledger::post($branch, 'deal_close', $date, __('Loss of deal :number', ['number' => $deal->number]).' · '.$deal->name, [
                    ['account' => Accounts::expense($branch, AccountCategory::byKey('trade_loss')), 'debit' => $held],
                    ['account' => $deal->account_id, 'credit' => $held],
                ], $deal);
            } elseif (Num::isNegative($held)) {
                Ledger::post($branch, 'deal_close', $date, __('Profit of deal :number', ['number' => $deal->number]).' · '.$deal->name, [
                    ['account' => $deal->account_id, 'debit' => Num::abs($held)],
                    ['account' => Accounts::expense($branch, AccountCategory::byKey('trade_income')), 'credit' => Num::abs($held)],
                ], $deal);
            }
            $deal->update(['status' => 'closed', 'closed_on' => $date, 'result' => Num::money(Num::neg($held))]);
            audit('closed', $deal, 'Deal '.$deal->number.' '.$deal->result);

            return $deal;
        });
    }

    /**
     * The cash of one box, counted by hand. The books are brought to what was counted: cash that
     * is missing is an expense, cash that is too much is an income.
     */
    public static function countCash(Account $account, mixed $counted, ?string $note = null, ?string $date = null): CashCount
    {
        $counted = Num::money($counted);
        if (Num::isNegative($counted)) {
            throw new UserError(__('app.errors.negative_amount'));
        }
        $account->loadMissing('currency');
        if ((int) $account->account_category_id !== (int) AccountCategory::byKey('cash')->id) {
            throw new UserError(__('Choose a cash account.'));
        }

        return DB::transaction(function () use ($account, $counted, $note, $date) {
            $branch = Branch::main();
            $date ??= today_iso();
            $book = Ledger::balance($account);
            $difference = Num::money(Num::sub($counted, $book));

            $count = CashCount::create([
                'branch_id' => $branch->id, 'number' => Sequence::next('CC'), 'date' => $date, 'account_id' => $account->id, 'currency_id' => $account->currency_id,
                'book' => $book, 'counted' => $counted, 'difference' => $difference, 'note' => $note, 'created_by' => auth()->id(),
            ]);

            if (! Num::isZero($difference)) {
                $base = Rates::toBase(Num::abs($difference), $account->currency, $branch);
                $lines = Num::isPositive($difference)
                    ? [['account' => $account, 'debit' => $difference, 'base' => $base], ['account' => Accounts::expense($branch, AccountCategory::byKey('cash_excess')), 'credit' => $base]]
                    : [['account' => Accounts::expense($branch, AccountCategory::byKey('cash_shortage')), 'debit' => $base], ['account' => $account, 'credit' => Num::abs($difference), 'base' => $base]];
                Ledger::post($branch, 'cash_count', $date, $count->number.' · '.$account->label().' '.$account->currency->code, $lines, $count);
            }
            audit('created', $count, 'Cash count '.$count->number.' '.$difference.' '.$account->currency->code);

            return $count;
        });
    }

    public static function cancelCount(CashCount $count, ?string $reason = null): void
    {
        if ($count->isCancelled()) {
            return;
        }
        DB::transaction(function () use ($count, $reason) {
            $count->update(['cancelled_at' => now()]);
            Ledger::voidSource($count, $reason);
        });
    }
}
