<?php

namespace App\Services;

use App\Exceptions\UserError;
use App\Models\Account;
use App\Models\AccountCategory;
use App\Models\AccountGroup;
use App\Models\Branch;
use App\Models\Currency;
use App\Models\Party;
use App\Models\Voucher;
use App\Support\Num;
use Illuminate\Support\Facades\DB;

/**
 * The daily papers of a business. Each one posts a single balanced entry in the journal;
 * the people who record them never see a debit or a credit.
 *
 *   money in    a party gives money: it comes into a money account, and the business owes it to the party
 *   money out   the business pays a party from a money account
 *   transfer    money moves between two money accounts of the same currency, less a fee if one was taken
 *   exchange    one currency is given and another received; the difference from the rates of the
 *               business is the profit or loss of the deal
 *   income      money earned that is not an exchange: the profit of a trade, a commission, a rent
 */
class Vouchers
{
    /** A party hands money to the business (a deposit, a repayment of what the party owed). */
    public static function moneyIn(Party $party, Account $money, string $date, mixed $amount, ?string $due = null, ?string $description = null): Voucher
    {
        return self::withParty(Voucher::MONEY_IN, $party, $money, $date, $amount, $due, $description);
    }

    /** The business pays a party (paying back a deposit, paying a supplier, lending). */
    public static function moneyOut(Party $party, Account $money, string $date, mixed $amount, ?string $description = null): Voucher
    {
        return self::withParty(Voucher::MONEY_OUT, $party, $money, $date, $amount, null, $description);
    }

    private static function withParty(string $kind, Party $party, Account $money, string $date, mixed $amount, ?string $due, ?string $description): Voucher
    {
        $amount = self::amount($amount);
        self::mustBeMoney($money);
        $party->loadMissing('group');
        if ($party->group->isMoney()) {
            throw new UserError(__('Between two money accounts use a transfer or an exchange.'));
        }
        if ($party->is_active === false) {
            throw new UserError(__(':name is switched off.', ['name' => $party->name]));
        }

        return DB::transaction(function () use ($kind, $party, $money, $date, $amount, $due, $description) {
            $branch = Branch::main();
            $account = Accounts::party($party, $money->currency, $branch);
            $in = $kind === Voucher::MONEY_IN;
            $base = Rates::toBase($amount, $money->currency, $branch);

            $voucher = self::create($kind, $branch, $date, [
                'party_id' => $party->id, 'account_id' => $money->id, 'other_account_id' => $account->id,
                'currency_id' => $money->currency_id, 'amount' => $amount, 'base_amount' => $base,
                'due_date' => $in ? $due : null, 'description' => $description,
            ]);

            Ledger::post($branch, $kind, $date, self::line($voucher, $party->name, $description), [
                ['account' => $in ? $money : $account, 'debit' => $amount, 'base' => $base],
                ['account' => $in ? $account : $money, 'credit' => $amount, 'base' => $base],
            ], $voucher);

            return $voucher;
        });
    }

    /** Money moves between two money accounts of the same currency. The fee, if any, is paid from the first one. */
    public static function transfer(Account $from, Account $to, string $date, mixed $amount, mixed $fee = 0, ?string $description = null): Voucher
    {
        $amount = self::amount($amount);
        $fee = Num::money($fee ?: 0);
        if (Num::isNegative($fee)) {
            throw new UserError(__('app.errors.negative_amount'));
        }
        self::mustBeMoney($from);
        self::mustBeMoney($to);
        if ((int) $from->id === (int) $to->id) {
            throw new UserError(__('Choose two different accounts.'));
        }
        if ((int) $from->currency_id !== (int) $to->currency_id) {
            throw new UserError(__('A transfer is between accounts of the same currency. Between two currencies record an exchange.'));
        }

        return DB::transaction(function () use ($from, $to, $date, $amount, $fee, $description) {
            $branch = Branch::main();
            $base = Rates::toBase($amount, $from->currency, $branch);

            $voucher = self::create(Voucher::TRANSFER, $branch, $date, [
                'account_id' => $from->id, 'other_account_id' => $to->id, 'currency_id' => $from->currency_id,
                'amount' => $amount, 'fee' => $fee, 'base_amount' => $base, 'description' => $description,
            ]);

            $lines = [
                ['account' => $to, 'debit' => $amount, 'base' => $base],
                ['account' => $from, 'credit' => $amount, 'base' => $base],
            ];
            if (Num::isPositive($fee)) {
                $feeBase = Rates::toBase($fee, $from->currency, $branch);
                $lines[] = ['account' => Accounts::expense($branch, AccountCategory::byKey('expense_fees')), 'debit' => $feeBase];
                $lines[] = ['account' => $from, 'credit' => $fee, 'base' => $feeBase];
            }
            Ledger::post($branch, Voucher::TRANSFER, $date, self::line($voucher, $from->label().' → '.$to->label(), $description), $lines, $voucher);

            return $voucher;
        });
    }

    /**
     * One currency is given from a money account and another is received into a money account.
     * Both are valued at the rates of the business; what was received above what was given is
     * the profit of the deal (below it, the loss), and the journal books it by itself.
     */
    public static function exchange(Account $from, Account $to, string $date, mixed $given, mixed $received, ?string $description = null): Voucher
    {
        $given = self::amount($given);
        $received = self::amount($received);
        self::mustBeMoney($from);
        self::mustBeMoney($to);
        if ((int) $from->currency_id === (int) $to->currency_id) {
            throw new UserError(__('An exchange is between two different currencies.'));
        }

        return DB::transaction(function () use ($from, $to, $date, $given, $received, $description) {
            $branch = Branch::main();
            $out = Rates::toBase($given, $from->currency, $branch);
            $in = Rates::toBase($received, $to->currency, $branch);

            $voucher = self::create(Voucher::EXCHANGE, $branch, $date, [
                'account_id' => $from->id, 'other_account_id' => $to->id,
                'currency_id' => $from->currency_id, 'amount' => $given,
                'other_currency_id' => $to->currency_id, 'other_amount' => $received,
                'rate' => Num::round(Num::div($received, $given), 6),
                'base_amount' => $out, 'profit' => Num::money(Num::sub($in, $out)), 'description' => $description,
            ]);

            Ledger::post($branch, Voucher::EXCHANGE, $date, self::line($voucher, money($given, $from->currency->code).' → '.money($received, $to->currency->code), $description), [
                ['account' => $to, 'debit' => $received, 'base' => $in],
                ['account' => $from, 'credit' => $given, 'base' => $out],
            ], $voucher);

            return $voucher;
        });
    }

    /** Money earned that is not an exchange, under one of the income categories. */
    public static function income(AccountCategory $category, Account $money, string $date, mixed $amount, ?string $description = null): Voucher
    {
        $amount = self::amount($amount);
        self::mustBeMoney($money);
        if ($category->nature !== 'income') {
            throw new UserError(__('Choose an income category.'));
        }

        return DB::transaction(function () use ($category, $money, $date, $amount, $description) {
            $branch = Branch::main();
            $base = Rates::toBase($amount, $money->currency, $branch);
            $income = Accounts::expense($branch, $category);

            $voucher = self::create(Voucher::INCOME, $branch, $date, [
                'account_id' => $money->id, 'other_account_id' => $income->id, 'currency_id' => $money->currency_id,
                'amount' => $amount, 'base_amount' => $base, 'description' => $description,
            ]);

            Ledger::post($branch, Voucher::INCOME, $date, self::line($voucher, $category->name, $description), [
                ['account' => $money, 'debit' => $amount, 'base' => $base],
                ['account' => $income, 'credit' => $base],
            ], $voucher);

            return $voucher;
        });
    }

    /** A paper is never deleted: it is cancelled, its entry is taken back, and it stays on the list crossed out. */
    public static function cancel(Voucher $voucher, ?string $reason = null): void
    {
        if ($voucher->isCancelled()) {
            return;
        }

        DB::transaction(function () use ($voucher, $reason) {
            $voucher->update(['cancelled_at' => now(), 'cancel_reason' => $reason ? mb_substr($reason, 0, 255) : null]);
            Ledger::voidSource($voucher, $reason);
            audit('cancelled', $voucher, trim($voucher->number.' '.($reason ?? '')));
        });
    }

    /**
     * What a party has with the business in each currency, from the side of the business:
     * a positive amount is owed to the party, a negative one is owed by the party.
     *
     * @return array<int, array{currency: Currency, amount: string}>
     */
    public static function balances(Party $party): array
    {
        $party->loadMissing(['accounts.total', 'accounts.currency', 'group']);
        $out = [];
        foreach ($party->accounts->sortBy('currency_id') as $account) {
            $balance = Num::money($account->total?->balance ?? 0);
            if (Num::isZero($balance)) {
                continue;
            }
            // a money account holds a debit balance; a party we owe holds a credit balance
            $out[] = ['currency' => $account->currency, 'amount' => $party->group->type === AccountGroup::MONEY ? $balance : Num::money(Num::neg($balance))];
        }

        return $out;
    }

    private static function create(string $kind, Branch $branch, string $date, array $data): Voucher
    {
        $voucher = Voucher::create($data + [
            'branch_id' => $branch->id, 'kind' => $kind, 'date' => $date,
            'number' => Sequence::next(Voucher::KINDS[$kind][1]), 'created_by' => auth()->id(),
        ]);
        audit('created', $voucher, Voucher::KINDS[$kind][0].' '.$voucher->number.' '.$voucher->amount);

        return $voucher;
    }

    private static function line(Voucher $voucher, string $what, ?string $description): string
    {
        return trim($voucher->number.' · '.$what.($description ? ' · '.$description : ''));
    }

    private static function amount(mixed $amount): string
    {
        $amount = Num::money($amount);
        if (! Num::isPositive($amount)) {
            throw new UserError(__('The amount must be more than zero.'));
        }

        return $amount;
    }

    private static function mustBeMoney(Account $account): void
    {
        $account->loadMissing('currency');
        if (! in_array((int) $account->account_category_id, AccountCategory::moneyIds(), true) || ! $account->is_active) {
            throw new UserError(__('Choose a cash, bank or saraf account.'));
        }
    }
}
