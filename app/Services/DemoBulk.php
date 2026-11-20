<?php

namespace App\Services;

use App\Models\AccountGroup;
use App\Models\Branch;
use App\Models\Currency;
use App\Models\Party;
use App\Models\Voucher;
use Illuminate\Support\Facades\DB;

/**
 * The years of daily work behind the demo: about fifty accounts in every section and as many
 * papers as asked for (money in, money out and transfers), each with its journal entry. They are
 * written straight into the tables in blocks, because posting a hundred thousand papers one by
 * one would take an hour; what the ledger would have done (numbers, balanced lines, the running
 * balance of every account, no money account below zero) is done here by hand.
 */
class DemoBulk
{
    private const FIRST = ['Ahmad', 'Mohammad', 'Abdul', 'Sayed', 'Haji', 'Noor', 'Gul', 'Khan', 'Mir', 'Shah', 'Wali', 'Zabiullah', 'Hamid', 'Farid', 'Najib', 'Rahim', 'Karim', 'Jawad', 'Bashir', 'Qais',
        'Nasir', 'Samiullah', 'Habib', 'Rafi', 'Yama', 'Omid', 'Parwiz', 'Sharif', 'Latif', 'Tariq', 'Masood', 'Ehsan', 'Faisal', 'Idris', 'Jamil', 'Daud', 'Asad', 'Shafiq', 'Wahid', 'Zaki'];

    private const LAST = ['Ahmadi', 'Noorzai', 'Popal', 'Stanikzai', 'Hashimi', 'Karimi', 'Rahimi', 'Safi', 'Wardak', 'Durrani', 'Barakzai', 'Mohammadi', 'Azizi', 'Sultani', 'Haidari', 'Amiri', 'Yousufzai', 'Sahak',
        'Alokozai', 'Hotak', 'Faizi', 'Naseri', 'Qaderi', 'Sediqi', 'Omari', 'Jalali', 'Mansoori', 'Zaheer', 'Rasuli', 'Nazari', 'Habibi', 'Shinwari', 'Kakar', 'Mangal', 'Tokhi', 'Zadran', 'Andar', 'Achakzai', 'Samadi', 'Ibrahimi'];

    private const TRADES = ['Trading', 'Super Store', 'Pharmacy', 'Restaurant', 'Bakery', 'Construction Materials', 'Mobile Shop', 'Carpets', 'Electronics', 'Wholesale', 'Tailoring', 'Auto Parts', 'Grocery', 'Hotel', 'Printing Press'];

    private const SUPPLIES = ['Importers', 'Wholesale', 'Flour Mill', 'Food Industries', 'Dry Fruits Co.', 'Transport', 'Plastic Factory', 'Oil & Ghee', 'Traders', 'Logistics', 'Packaging', 'Tea House Importers', 'Steel Mill', 'Beverages', 'Distribution'];

    private const PLACES = ['Mandawi, Kabul', 'Shahr-e Naw, Kabul', 'Karte Naw, Kabul', 'Khair Khana, Kabul', 'Kote Sangi, Kabul', 'Dasht-e Barchi, Kabul', 'Taimani, Kabul', 'Pul-e Charkhi, Kabul', 'Herat', 'Mazar-i-Sharif',
        'Kandahar', 'Jalalabad', 'Kunduz', 'Ghazni', 'Charikar', 'Pul-e Khumri', 'Khost', 'Gardez', 'Lashkargah', 'Taloqan'];

    private const BANKS = ['Azizi Bank', 'Afghanistan International Bank', 'New Kabul Bank', 'Bank-e-Millie Afghan', 'Pashtany Bank', 'Maiwand Bank', 'Ghazanfar Bank', 'Bakhtar Bank', 'Afghan United Bank', 'Islamic Bank of Afghanistan', 'First MicroFinance Bank'];

    private const MARKETS = ['Sarai Shahzada, Kabul', 'Khorasan Market, Herat', 'Kefayat Market, Mazar-i-Sharif', 'Shahidan Chowk, Kandahar', 'Sarai Shahzada, Kabul', 'Talashi Chowk, Jalalabad'];

    private const IN = ['Paid on account', 'Deposit (amanat)', 'Cash received', 'Received against invoice', 'Payment for goods', 'Advance for an order', 'Returned loan'];

    private const OUT = ['Payment on account', 'Deposit paid back', 'Paid for goods', 'Advance', 'Refund', 'Loan', 'Paid against invoice'];

    private const MOVE = ['Deposit to the bank', 'Cash taken from the bank', 'Sent to the saraf', 'Taken back from the saraf', 'Between accounts'];

    /**
     * @param  int  $count  papers to write
     * @param  int  $days  over how many days they are spread
     * @param  int  $skip  the newest of them is this many days old (the days after it belong to the story of the demo)
     */
    public static function run(int $count, int $days, int $skip): void
    {
        $main = Branch::main();
        $user = auth()->id();
        $currencies = Currency::active()->orderBy('sort')->get()->keyBy('code');
        $factor = [];
        foreach ($currencies as $code => $currency) {
            $factor[$code] = (float) Rates::toBase((string) $currency->quote_unit, $currency, $main) / (int) $currency->quote_unit;
        }
        $name = fn (int $n) => self::FIRST[($n * 7 + 3) % count(self::FIRST)].' '.self::LAST[($n * 11 + 5) % count(self::LAST)];
        $phone = fn (int $n) => sprintf('07%02d %03d %03d', [0, 99, 88, 77, 44, 28, 73, 95, 86, 7][$n % 10], ($n * 37 + 211) % 1000, ($n * 113 + 47) % 1000);
        $make = fn (string $group, string $label, int $n, ?string $address) => Party::create(['account_group_id' => AccountGroup::byKey($group)->id, 'name' => $label, 'phone' => $phone($n), 'address' => $address]);

        // every money account, per currency; the first of each currency is the cash box
        $money = [];
        foreach ($currencies as $code => $currency) {
            $money[$code] = [(int) Accounts::cash($main, $currency)->id];
        }
        $banks = AccountGroup::byKey('bank');
        for ($n = 0; $n < 49; $n++) {
            $bank = Party::create(['account_group_id' => $banks->id, 'name' => self::BANKS[$n % count(self::BANKS)].' - '.['current', 'savings', 'business'][$n % 3].' account '.sprintf('%04d-%04d', 1000 + $n * 83 % 9000, 1000 + $n * 577 % 9000),
                'address' => self::PLACES[$n % count(self::PLACES)]]);
            $money['AFN'][] = (int) Accounts::party($bank, $currencies['AFN'], $main)->id;
            if ($n % 3 === 0 && isset($currencies['USD'])) {
                $money['USD'][] = (int) Accounts::party($bank, $currencies['USD'], $main)->id;
            }
        }
        for ($n = 0; $n < 49; $n++) {
            $saraf = $make('saraf', 'Haji '.$name($n + 300).' Saraf, '.self::MARKETS[$n % count(self::MARKETS)], $n + 300, null);
            foreach ($currencies as $code => $currency) {
                if ($code === 'AFN' || $code === 'USD' || $n % 4 === 0) {
                    $money[$code][] = (int) Accounts::party($saraf, $currency, $main)->id;
                }
            }
        }
        // customers and suppliers: half of them people, half of them businesses
        $parties = [];
        for ($n = 0; $n < 42; $n++) {
            $label = $n % 2 ? $name($n + 100) : self::LAST[($n * 3 + 1) % count(self::LAST)].' '.self::TRADES[$n % count(self::TRADES)];
            $parties[] = ['party' => $make('customers', $label, $n + 100, self::PLACES[($n * 3) % count(self::PLACES)]), 'in' => 56, 'accounts' => []];
        }
        for ($n = 0; $n < 46; $n++) {
            $label = self::LAST[($n * 7 + 2) % count(self::LAST)].' '.self::SUPPLIES[$n % count(self::SUPPLIES)];
            $parties[] = ['party' => $make('suppliers', $label, $n + 200, self::PLACES[($n * 5 + 8) % count(self::PLACES)]), 'in' => 40, 'accounts' => []];
        }
        if ($count < 1) {
            return;
        }

        $sequence = fn (string $prefix) => (int) (DB::table('settings')->where('key', 'seq.'.$prefix)->value('value') ?? 0);
        $numbers = ['MI' => $sequence('MI'), 'MO' => $sequence('MO'), 'TR' => $sequence('TR'), 'JV' => $sequence('JV')];
        $voucherId = (int) DB::table('vouchers')->max('id');
        $entryId = (int) DB::table('journal_entries')->max('id');
        $source = (new Voucher)->getMorphClass();
        $balance = [];                                          // account => [own currency, base]
        $held = [];                                             // what each money account holds, so none goes below zero
        $pick = [];
        foreach ($currencies as $code => $currency) {
            $pick = array_merge($pick, array_fill(0, ['AFN' => 68, 'USD' => 24, 'EUR' => 4][$code] ?? 2, $code));
        }
        $vouchers = $entries = $lines = [];
        $flush = function () use (&$vouchers, &$entries, &$lines) {
            foreach (array_chunk($vouchers, 400) as $chunk) {
                DB::table('vouchers')->insert($chunk);
            }
            foreach (array_chunk($entries, 500) as $chunk) {
                DB::table('journal_entries')->insert($chunk);
            }
            foreach (array_chunk($lines, 800) as $chunk) {
                DB::table('journal_lines')->insert($chunk);
            }
            $vouchers = $entries = $lines = [];
        };

        DB::transaction(function () use ($count, $days, $skip, $main, $user, $currencies, $factor, $money, &$parties, &$numbers, &$voucherId, &$entryId, $source, &$balance, &$held, $pick, &$vouchers, &$entries, &$lines, $flush) {
            for ($i = 0; $i < $count; $i++) {
                $ago = $skip + $days - intdiv($i * $days, $count);
                $moment = now()->subDays($ago)->setTime(mt_rand(8, 17), mt_rand(0, 59), mt_rand(0, 59));
                if ($moment->isFriday()) {
                    $moment->subDay();                           // nothing is recorded on a Friday
                }
                $date = $moment->toDateString();
                $stamp = $moment->toDateTimeString();
                $code = $pick[mt_rand(0, count($pick) - 1)];
                $currency = $currencies[$code];
                $amount = match ($code) {
                    'AFN' => mt_rand(1, 20) * mt_rand(1, 20) * 500,
                    'PKR' => mt_rand(1, 20) * mt_rand(1, 10) * 5000,
                    default => mt_rand(1, 20) * mt_rand(1, 10) * 50,
                };
                $base = round($amount * $factor[$code], 2);
                $accounts = $money[$code];
                $box = mt_rand(1, 100) <= 55 || count($accounts) === 1 ? $accounts[0] : $accounts[mt_rand(1, count($accounts) - 1)];

                $roll = mt_rand(1, 100);
                $kind = Voucher::MONEY_IN;
                $other = null;
                if ($roll > 88 && count($accounts) > 1) {
                    // money moves between two of the business's own accounts
                    $to = $accounts[mt_rand(0, count($accounts) - 1)];
                    if ($to !== $box && ($held[$box] ?? 0) >= $amount) {
                        $kind = Voucher::TRANSFER;
                        $other = $to;
                    }
                }
                $who = null;
                if ($kind !== Voucher::TRANSFER) {
                    $index = mt_rand(0, count($parties) - 1);
                    $who = $parties[$index]['party'];
                    $other = $parties[$index]['accounts'][$code] ??= (int) Accounts::party($who, $currency, $main)->id;
                    if (mt_rand(1, 100) > $parties[$index]['in'] && ($held[$box] ?? 0) >= $amount + 20000) {
                        $kind = Voucher::MONEY_OUT;
                    }
                }

                [$prefix, $words] = match ($kind) {
                    Voucher::MONEY_IN => ['MI', self::IN], Voucher::MONEY_OUT => ['MO', self::OUT], default => ['TR', self::MOVE],
                };
                $number = sprintf('%s-%06d', $prefix, ++$numbers[$prefix]);
                $text = $words[mt_rand(0, count($words) - 1)];
                // which account is debited and which credited
                [$debit, $credit] = match ($kind) {
                    Voucher::MONEY_IN => [$box, $other], Voucher::MONEY_OUT => [$other, $box], default => [$other, $box],
                };
                $held[$debit] = ($held[$debit] ?? 0) + $amount;
                $held[$credit] = ($held[$credit] ?? 0) - $amount;
                foreach ([[$debit, 1], [$credit, -1]] as [$account, $sign]) {
                    $balance[$account] ??= [0, 0.0];
                    $balance[$account][0] += $sign * $amount;
                    $balance[$account][1] += $sign * $base;
                }

                $vouchers[] = ['id' => ++$voucherId, 'branch_id' => $main->id, 'number' => $number, 'kind' => $kind, 'date' => $date, 'party_id' => $who?->id, 'account_id' => $box, 'other_account_id' => $other,
                    'currency_id' => $currency->id, 'amount' => $amount, 'fee' => 0, 'base_amount' => $base, 'profit' => 0, 'description' => $text, 'created_by' => $user, 'created_at' => $stamp, 'updated_at' => $stamp];
                $entries[] = ['id' => ++$entryId, 'branch_id' => $main->id, 'number' => sprintf('JV-%06d', ++$numbers['JV']), 'date' => $date, 'type' => $kind,
                    'description' => $number.' · '.($who?->name ?? $text).($who ? ' · '.$text : ''), 'source_type' => $source, 'source_id' => $voucherId, 'created_by' => $user, 'created_at' => $stamp, 'updated_at' => $stamp];
                $line = ['journal_entry_id' => $entryId, 'branch_id' => $main->id, 'currency_id' => $currency->id, 'date' => $date, 'memo' => null, 'is_void' => false];
                $lines[] = $line + ['account_id' => $debit, 'debit' => $amount, 'credit' => 0, 'base_debit' => $base, 'base_credit' => 0];
                $lines[] = $line + ['account_id' => $credit, 'debit' => 0, 'credit' => $amount, 'base_debit' => 0, 'base_credit' => $base];
                if (count($vouchers) >= 2000) {
                    $flush();
                }
            }
            $flush();

            foreach ($numbers as $prefix => $last) {
                DB::table('settings')->updateOrInsert(['key' => 'seq.'.$prefix], ['value' => (string) $last]);
            }
            $before = DB::table('account_balances')->whereIn('account_id', array_keys($balance))->get()->keyBy('account_id');
            $rows = [];
            foreach ($balance as $account => [$own, $inBase]) {
                $rows[] = ['account_id' => $account, 'balance' => round($own + (float) ($before[$account]->balance ?? 0), 2), 'base_balance' => round($inBase + (float) ($before[$account]->base_balance ?? 0), 2), 'updated_at' => now()];
            }
            foreach (array_chunk($rows, 300) as $chunk) {
                DB::table('account_balances')->upsert($chunk, ['account_id'], ['balance', 'base_balance', 'updated_at']);
            }
        });
    }
}
