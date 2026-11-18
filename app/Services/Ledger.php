<?php

namespace App\Services;

use App\Exceptions\UserError;
use App\Models\Account;
use App\Models\AccountBalance;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Currency;
use App\Models\JournalEntry;
use App\Models\JournalLine;
use App\Support\Num;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * The one journal behind every screen.
 *
 * An entry belongs to one branch and uses only accounts of that branch, so every branch
 * has complete books of its own. Each line is kept twice: in the currency of its account
 * and as its value in AFN. An entry always balances in AFN. When currencies meet, the
 * difference between what came in and what went out, both at the branch's rate, is the
 * profit or loss of the exchange and goes to "Income from currency exchange" by itself.
 */
class Ledger
{
    /**
     * @param  array<int, array{account: Account|int, debit?: mixed, credit?: mixed, base?: mixed, memo?: ?string}>  $lines
     *                                                                                                                      "base" is the AFN value; without it the branch's rate is used
     */
    public static function post(Branch $branch, string $type, string $date, ?string $description, array $lines, ?Model $source = null): JournalEntry
    {
        return DB::transaction(function () use ($branch, $type, $date, $description, $lines, $source) {
            Days::guard($branch, $date);
            $baseId = (int) Currency::base()->id;
            $rows = [];
            $foreign = false;
            $debits = '0';
            $credits = '0';

            foreach ($lines as $line) {
                $account = $line['account'] instanceof Account ? $line['account'] : Account::with('currency')->findOrFail($line['account']);
                if ((int) $account->branch_id !== (int) $branch->id) {
                    throw new UserError(__('The account :name belongs to another branch.', ['name' => $account->name]));
                }

                $debit = Num::money($line['debit'] ?? 0);
                $credit = Num::money($line['credit'] ?? 0);
                if (Num::isNegative($debit) || Num::isNegative($credit)) {
                    throw new UserError(__('app.errors.negative_amount'));
                }
                if (Num::isPositive($debit) && Num::isPositive($credit)) {
                    throw new UserError(__('A line can have a debit or a credit, not both.'));
                }

                $isDebit = Num::isPositive($debit) || (Num::isZero($credit) && ($line['side'] ?? 'debit') === 'debit');
                $amount = $isDebit ? $debit : $credit;
                $base = isset($line['base']) ? Num::money($line['base']) : Rates::toBase($amount, $account->currency, $branch);
                if (Num::isNegative($base)) {
                    throw new UserError(__('app.errors.negative_amount'));
                }
                // a line can exist in AFN only (a revaluation: the value changes, the money does not)
                if (Num::isZero($amount) && Num::isZero($base)) {
                    continue;
                }
                if ((int) $account->currency_id !== $baseId) {
                    $foreign = true;
                }

                $rows[] = [
                    'branch_id' => $branch->id,
                    'account_id' => $account->id,
                    'currency_id' => $account->currency_id,
                    'date' => $date,
                    'debit' => $isDebit ? $amount : '0',
                    'credit' => $isDebit ? '0' : $amount,
                    'base_debit' => $isDebit ? $base : '0',
                    'base_credit' => $isDebit ? '0' : $base,
                    'memo' => isset($line['memo']) ? mb_substr((string) $line['memo'], 0, 500) : null,
                ];
                $debits = Num::add($debits, $isDebit ? $base : '0');
                $credits = Num::add($credits, $isDebit ? '0' : $base);
            }

            if (count($rows) < 2) {
                throw new UserError(__('app.errors.empty_entry'));
            }

            $difference = Num::money(Num::sub($debits, $credits));
            if (! Num::isZero($difference)) {
                if (! $foreign) {
                    throw new UserError(__('app.errors.unbalanced'));
                }
                // more value came in than went out: a profit on the exchange
                $gain = Num::isPositive($difference);
                $income = Accounts::system($branch, 'exchange_income');
                $rows[] = [
                    'branch_id' => $branch->id,
                    'account_id' => $income->id,
                    'currency_id' => $income->currency_id,
                    'date' => $date,
                    'debit' => $gain ? '0' : Num::abs($difference),
                    'credit' => $gain ? $difference : '0',
                    'base_debit' => $gain ? '0' : Num::abs($difference),
                    'base_credit' => $gain ? $difference : '0',
                    'memo' => null,
                ];
            }

            $entry = JournalEntry::create([
                'branch_id' => $branch->id,
                'number' => Sequence::next('JV'),
                'date' => $date,
                'type' => $type,
                'description' => $description ? mb_substr($description, 0, 500) : null,
                'source_type' => $source ? $source->getMorphClass() : null,
                'source_id' => $source?->getKey(),
                'created_by' => auth()->id(),
            ]);

            foreach ($rows as $row) {
                JournalLine::create($row + ['journal_entry_id' => $entry->id]);
                self::move($row['account_id'], Num::sub($row['debit'], $row['credit']), Num::sub($row['base_debit'], $row['base_credit']));
            }
            self::checkCash(array_column($rows, 'account_id'));

            return $entry;
        });
    }

    /**
     * Cancel an entry. On the day it was made it is crossed out in the day book. Once its
     * day is closed nothing in that day changes any more: a second entry, dated today,
     * takes it back, and both stay in the books.
     */
    public static function void(JournalEntry $entry, ?string $reason = null): void
    {
        if ($entry->isVoid() || $entry->reversed_by_id) {
            return;
        }

        $sameDay = $entry->date->toDateString() === today_iso();
        if (! $sameDay) {
            self::reverse($entry, $reason);

            return;
        }

        DB::transaction(function () use ($entry, $reason) {
            $entry->update(['voided_at' => now(), 'voided_by' => auth()->id(), 'void_reason' => $reason ? mb_substr($reason, 0, 255) : null]);

            foreach ($entry->lines()->where('is_void', false)->get() as $line) {
                self::move($line->account_id, Num::sub($line->credit, $line->debit), Num::sub($line->base_credit, $line->base_debit));
            }
            $entry->lines()->update(['is_void' => true]);
            self::checkCash($entry->lines()->pluck('account_id')->all());

            AuditLog::record('cancelled', $entry, trim($entry->number.' '.$entry->description.($reason ? ' ('.$reason.')' : '')));
        });
    }

    /** Take back an entry of a closed day with a second entry dated today. */
    private static function reverse(JournalEntry $entry, ?string $reason): void
    {
        DB::transaction(function () use ($entry, $reason) {
            $lines = [];
            foreach ($entry->lines()->where('is_void', false)->orderBy('id')->get() as $line) {
                $wasDebit = Num::isPositive($line->debit) || Num::isPositive($line->base_debit);
                $lines[] = $wasDebit
                    ? ['account' => (int) $line->account_id, 'credit' => $line->debit, 'base' => $line->base_debit, 'side' => 'credit']
                    : ['account' => (int) $line->account_id, 'debit' => $line->credit, 'base' => $line->base_credit, 'side' => 'debit'];
            }

            $reversal = self::post($entry->branch, 'reversal', today_iso(), trim(__('Takes back :number', ['number' => $entry->number]).($reason ? ': '.$reason : '')), $lines);
            $reversal->update(['reversal_of_id' => $entry->id]);
            $entry->update(['reversed_by_id' => $reversal->id, 'void_reason' => $reason ? mb_substr($reason, 0, 255) : null]);

            AuditLog::record('cancelled', $entry, trim($entry->number.' '.$entry->description.' → '.$reversal->number.($reason ? ' ('.$reason.')' : '')));
        });
    }

    /** Cancel every entry that a paper (an exchange, a remittance, an expense ...) created. */
    public static function voidSource(Model $source, ?string $reason = null): void
    {
        JournalEntry::where('source_type', $source->getMorphClass())
            ->where('source_id', $source->getKey())
            ->whereNull('voided_at')
            ->whereNull('reversed_by_id')
            ->get()
            ->each(fn (JournalEntry $entry) => self::void($entry, $reason));
    }

    /** Balance in the account's own currency: debits minus credits. */
    public static function balance(Account|int $account): string
    {
        return Num::money(AccountBalance::find($account instanceof Account ? $account->id : $account)?->balance ?? 0);
    }

    /** Balance as its value in AFN. */
    public static function baseBalance(Account|int $account): string
    {
        return Num::money(AccountBalance::find($account instanceof Account ? $account->id : $account)?->base_balance ?? 0);
    }

    /** Balance at the end of a day (or just before it), added up from the lines. For statements. */
    public static function balanceOn(int $accountId, string $date, bool $before = false): string
    {
        $row = JournalLine::where('account_id', $accountId)
            ->where('is_void', false)
            ->whereDate('date', $before ? '<' : '<=', $date)
            ->selectRaw('COALESCE(SUM(debit),0) as debit, COALESCE(SUM(credit),0) as credit')
            ->toBase()
            ->first();

        return Num::money(Num::sub($row->debit ?? 0, $row->credit ?? 0));
    }

    /**
     * Cash in the drawer cannot be less than nothing: an entry (or a cancellation) that would
     * pay out more cash than the branch has is refused, and nothing of it is kept.
     */
    private static function checkCash(array $accountIds): void
    {
        $cash = Account::with('currency')
            ->whereIn('id', array_unique($accountIds))
            ->where('account_category_id', \App\Models\AccountCategory::byKey('cash')->id)
            ->get();

        foreach ($cash as $account) {
            $balance = self::balance($account);
            if (Num::isNegative($balance)) {
                throw new UserError(__('There is not enough cash in :account. It would be short by :amount.', [
                    'account' => $account->name,
                    'amount' => money(Num::abs($balance), $account->currency->code),
                ]));
            }
        }
    }

    /** Add to the running balance of an account. Runs inside the transaction of the entry. */
    private static function move(int $accountId, string $own, string $base): void
    {
        $row = AccountBalance::lockForUpdate()->find($accountId);
        if (! $row) {
            AccountBalance::create(['account_id' => $accountId, 'balance' => '0', 'base_balance' => '0']);
            $row = AccountBalance::lockForUpdate()->find($accountId);
        }

        $row->update([
            'balance' => Num::money(Num::add($row->balance, $own)),
            'base_balance' => Num::money(Num::add($row->base_balance, $base)),
            'updated_at' => now(),
        ]);
    }
}
