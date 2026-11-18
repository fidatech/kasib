<?php

namespace App\Services;

use App\Exceptions\UserError;
use App\Models\Account;
use App\Models\AccountCategory;
use App\Models\Branch;
use App\Models\Expense;
use App\Support\Num;
use Illuminate\Support\Facades\DB;

/**
 * An expense of a branch. It is paid from a cash or bank account, or left unpaid on a
 * liability account. The amount is in the currency of that account; the expense itself
 * is kept in AFN at the branch's rate, as the DAB statement wants it.
 */
class ExpenseService
{
    public static function record(Branch $branch, AccountCategory $category, string $date, mixed $amount, Account $paid, ?string $description = null): Expense
    {
        $amount = Num::money($amount);
        if (! Num::isPositive($amount)) {
            throw new UserError(__('The amount must be more than zero.'));
        }
        if ($category->nature !== 'expense') {
            throw new UserError(__('Choose an expense category.'));
        }
        if ((int) $paid->branch_id !== (int) $branch->id) {
            throw new UserError(__('The account :name belongs to another branch.', ['name' => $paid->name]));
        }

        return DB::transaction(function () use ($branch, $category, $date, $amount, $paid, $description) {
            $base = Rates::toBase($amount, $paid->currency, $branch);

            $expense = Expense::create([
                'branch_id' => $branch->id, 'account_category_id' => $category->id, 'date' => $date, 'paid_account_id' => $paid->id,
                'currency_id' => $paid->currency_id, 'amount' => $amount, 'base_amount' => $base, 'description' => $description, 'created_by' => auth()->id(),
            ]);

            Ledger::post($branch, 'expense', $date, trim($category->name.': '.$description, ': '), [
                ['account' => Accounts::expense($branch, $category), 'debit' => $base],
                ['account' => $paid, 'credit' => $amount, 'base' => $base],
            ], $expense);
            audit('created', $expense, 'Expense '.$category->name.' '.$amount.' '.$paid->currency->code);

            return $expense;
        });
    }

    public static function cancel(Expense $expense, ?string $reason = null): void
    {
        if ($expense->isCancelled()) {
            return;
        }

        DB::transaction(function () use ($expense, $reason) {
            $expense->update(['cancelled_at' => now()]);
            Ledger::voidSource($expense, $reason);
        });
    }
}
