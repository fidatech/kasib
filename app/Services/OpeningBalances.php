<?php

namespace App\Services;

use App\Exceptions\UserError;
use App\Models\AccountGroup;
use App\Models\Branch;
use App\Models\Currency;
use App\Models\Party;
use App\Models\RecurringExpense;
use App\Models\Notice;
use App\Models\User;
use App\Support\Num;
use Illuminate\Support\Facades\DB;

/**
 * Bringing an existing business into the system, and the work the system does by itself.
 *
 *  - Opening balances: a sheet of accounts and what each one starts with is read row by row.
 *    Either every row is taken or none: a sheet with a mistake changes nothing.
 *  - Recurring expenses: on its day of the month each one is recorded once.
 */
class OpeningBalances
{
    public const COLUMNS = ['Category', 'Name', 'Phone', 'Currency', 'Balance', 'Side'];

    /** Words accepted in the "Side" column. Nothing written: money accounts hold it, a party is owed it. */
    private const SIDES = ['we owe' => 'credit', 'we owe them' => 'credit', 'credit' => 'credit', 'owes us' => 'debit', 'they owe us' => 'debit', 'debit' => 'debit'];

    /**
     * @param  array<int, array<int, string>>  $rows  the sheet, its heading row included
     * @return array{accounts: int, balances: int}
     */
    public static function import(array $rows): array
    {
        $rows = array_values(array_filter($rows, fn (array $row) => trim(implode('', $row)) !== ''));
        // the heading row is left out, in whatever language it was written
        if ($rows && ! is_numeric(str_replace(',', '', (string) ($rows[0][4] ?? '')))) {
            array_shift($rows);
        }
        if (! $rows) {
            throw new UserError(__('The file has no rows.'));
        }
        if (count($rows) > 5000) {
            throw new UserError(__('A file can have 5,000 rows at most.'));
        }

        $groups = [];
        foreach (AccountGroup::active()->get() as $group) {
            foreach ([$group->key, $group->name, $group->name_fa, $group->name_ps, __($group->name)] as $word) {
                if ($word) {
                    $groups[mb_strtolower(trim($word))] = $group;
                }
            }
        }
        $currencies = Currency::active()->get()->keyBy(fn (Currency $c) => strtoupper($c->code));

        $errors = [];
        $clean = [];
        foreach ($rows as $i => $row) {
            $line = $i + 2;
            [$category, $name, $phone, $code, $balance, $side] = array_map(fn ($cell) => trim((string) $cell), array_pad($row, 6, ''));
            $group = $groups[mb_strtolower($category)] ?? null;
            $currency = $currencies[strtoupper($code ?: Currency::base()->code)] ?? null;
            $amount = str_replace(',', '', $balance === '' ? '0' : $balance);
            $sideKey = mb_strtolower($side);
            if (! $group) {
                $errors[] = __('Row :row: the category ":value" is not known.', ['row' => $line, 'value' => $category]);
            } elseif ($name === '') {
                $errors[] = __('Row :row: the name is empty.', ['row' => $line]);
            } elseif (! $currency) {
                $errors[] = __('Row :row: the currency ":value" is not in use.', ['row' => $line, 'value' => $code]);
            } elseif (! is_numeric($amount) || Num::isNegative($amount)) {
                $errors[] = __('Row :row: the balance ":value" is not an amount.', ['row' => $line, 'value' => $balance]);
            } elseif ($sideKey !== '' && ! isset(self::SIDES[$sideKey])) {
                $errors[] = __('Row :row: write "we owe" or "owes us" in the last column.', ['row' => $line]);
            } else {
                $debit = $group->isMoney() || (self::SIDES[$sideKey] ?? 'credit') === 'debit';
                $clean[] = [$group, mb_substr($name, 0, 150), mb_substr($phone, 0, 50), $currency, Num::money($amount), $debit];
            }
        }
        if ($errors) {
            throw new UserError(implode(' ', array_slice($errors, 0, 8)).(count($errors) > 8 ? ' '.__('And :count more.', ['count' => count($errors) - 8]) : ''));
        }

        return DB::transaction(function () use ($clean) {
            $branch = Branch::main();
            $equity = Accounts::system($branch, 'opening');
            $made = 0;
            $posted = 0;
            foreach ($clean as [$group, $name, $phone, $currency, $amount, $debit]) {
                $party = Party::where('account_group_id', $group->id)->where('name', $name)->first();
                if (! $party) {
                    $party = Party::create(['account_group_id' => $group->id, 'name' => $name, 'phone' => $phone ?: null])->fresh();
                    $made++;
                }
                $account = Accounts::party($party, $currency, $branch);
                if (! Num::isPositive($amount)) {
                    continue;
                }
                $base = Rates::toBase($amount, $currency, $branch);
                Ledger::post($branch, 'opening', today_iso(), __('Opening balance').' · '.$party->name, [
                    ['account' => $account, $debit ? 'debit' : 'credit' => $amount, 'base' => $base],
                    ['account' => $equity, $debit ? 'credit' : 'debit' => $base],
                ], $party);
                $posted++;
            }
            audit('imported', null, 'Opening balances: '.$made.' accounts, '.$posted.' balances');

            return ['accounts' => $made, 'balances' => $posted];
        });
    }

    /**
     * Record the recurring expenses whose day of this month has come. One that cannot be paid
     * (not enough cash) is left for the next night, and the owners are told.
     */
    public static function runRecurring(): int
    {
        $today = now();
        $posted = 0;
        $due = RecurringExpense::with(['category', 'paidAccount.currency'])->where('is_active', true)->where('day', '<=', (int) $today->format('j'))
            ->where(fn ($q) => $q->whereNull('last_posted_on')->orWhereDate('last_posted_on', '<', $today->copy()->startOfMonth()->toDateString()))->get();
        foreach ($due as $expense) {
            try {
                DB::transaction(function () use ($expense, $today) {
                    ExpenseService::record(Branch::main(), $expense->category, $today->toDateString(), $expense->amount, $expense->paidAccount, trim(($expense->description ?: $expense->category->name).' · '.$today->format('Y-m')));
                    $expense->update(['last_posted_on' => $today->toDateString()]);
                });
                $posted++;
            } catch (UserError $e) {
                foreach (User::role(User::ADMIN_ROLE)->where('is_active', true)->pluck('id') as $userId) {
                    Notice::send($userId, __('A recurring expense was not recorded'), ($expense->description ?: $expense->category->name).': '.$e->getMessage(), route('recurring-expenses.index'));
                }
            }
        }

        return $posted;
    }
}
