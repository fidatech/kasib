<?php

namespace App\Services;

use App\Models\AccountCategory;
use App\Models\AccountGroup;
use App\Models\Branch;
use App\Models\Central\Tenant;
use App\Models\Currency;
use App\Models\Invoice;
use App\Models\Item;
use App\Models\Party;
use App\Models\Setting;
use App\Models\User;
use App\Tenancy\Tenancy;
use App\Tenancy\TenantManager;
use Illuminate\Support\Facades\Auth;

/**
 * The demo: one business anybody may open. Its address, user and password are shown on the
 * website and on its own sign-in page. It never runs out. It is filled with example papers and rebuilt every midnight,
 * so whatever a visitor records in it is gone the next day.
 */
class Demo
{
    public static function slug(): string
    {
        return (string) config('tenancy.demo.slug');
    }

    public static function is(?Tenant $tenant): bool
    {
        return $tenant !== null && self::slug() !== '' && $tenant->slug === self::slug();
    }

    /** What the website shows: where the demo is and how to get in. Null while there is no demo. */
    public static function info(): ?array
    {
        static $info = false;
        if ($info === false || app()->runningUnitTests()) {
            $tenant = self::slug() !== '' ? Tenant::where('slug', self::slug())->where('status', Tenant::ACTIVE)->first() : null;
            $scheme = request()->isSecure() || app()->environment('production') ? 'https://' : 'http://';
            $info = $tenant ? ['host' => $tenant->domain, 'url' => $scheme.$tenant->domain.'/login', 'email' => (string) config('tenancy.demo.email'), 'password' => (string) config('tenancy.demo.password')] : null;
        }

        return $info;
    }

    /** Make the demo, or empty it and fill it again. */
    public static function refresh(): Tenant
    {
        $manager = app(TenantManager::class);
        $data = ['name' => 'Demo Trading Company', 'name_local' => 'شرکت تجارتی نمونه', 'slug' => self::slug(), 'plan' => 'pro',
            'owner_name' => 'Demo User', 'owner_email' => config('tenancy.demo.email'), 'owner_password' => config('tenancy.demo.password')];

        $tenant = Tenant::where('slug', self::slug())->first();
        if ($tenant) {
            $manager->rebuild($tenant, $data);
        } else {
            $tenant = $manager->create($data);
        }
        $tenant->forceFill(['name' => $data['name'], 'name_local' => $data['name_local'], 'plan' => 'pro', 'status' => Tenant::ACTIVE, 'locked_reason' => null,
            'licence_expires_on' => null, 'notes' => 'The demo of the website: no end date, never locked. Rebuilt every midnight by demo:refresh.'])->save();

        app(Tenancy::class)->run($tenant, fn () => self::fill());

        return $tenant;
    }

    /**
     * Two months of a food wholesaler in Kabul: the partners' capital, a bank account, purchases from
     * suppliers, sales over the counter and on credit, customers paying, suppliers paid, dollars
     * exchanged, the rent, the salaries and the small daily expenses. The same story is written every
     * night, moved to end on the day itself.
     */
    private static function fill(): void
    {
        $before = Auth::guard('web')->user();
        Auth::guard('web')->setUser(User::orderBy('id')->firstOrFail());
        mt_srand(20261119);
        try {
            $main = Branch::main();
            $afn = Currency::base();
            $usd = Currency::where('code', 'USD')->firstOrFail();
            Rates::set($main, $usd, '70.50', '70.30', '70.70');
            foreach (['EUR' => ['76.40', '76.10', '76.70'], 'PKR' => ['252.50', '251.00', '254.00']] as $code => [$rate, $buys, $sells]) {
                if ($other = Currency::active()->where('code', $code)->first()) {
                    Rates::set($main, $other, $rate, $buys, $sells);
                }
            }
            // the years before the two months told below: about fifty accounts a section and the papers of every day
            $volume = max(0, (int) config('tenancy.demo.transactions'));
            $years = $volume > 0 ? 730 : 0;
            foreach (['company_phone' => '0799 240 118', 'company_address' => 'Mandawi, Sarai Haji Zaman, Kabul'] as $key => $value) {
                Setting::updateOrCreate(['key' => $key], ['value' => $value]);
            }
            app()->forgetInstance('erp.settings');

            $day = fn (int $ago) => now()->subDays($ago)->toDateString();
            $party = fn (string $group, string $name, ?string $phone = null, ?string $address = null) => Party::create(['account_group_id' => AccountGroup::byKey($group)->id, 'name' => $name, 'phone' => $phone, 'address' => $address])->fresh();
            $category = fn (string $key) => AccountCategory::byKey($key);

            $cash = Accounts::cash($main, $afn);
            $dollars = Accounts::cash($main, $usd);
            $bank = Accounts::party($party('bank', 'Azizi Bank - current account 0041-2287'), $afn, $main);
            $saraf = Accounts::party($party('saraf', 'Haji Qasim Saraf, Sarai Shahzada', '0700 284 913'), $usd, $main);

            // the two partners pay in their capital, and most of it goes to the bank
            foreach ([['Haji Abdul Rahman Safi', 'حاجی عبدالرحمن صافی', 'Haji Gul Mohammad', '0799 240 118', 60, '1800000'], ['Mohammad Naeem Safi', 'محمد نعیم صافی', 'Haji Abdul Rahman', '0788 615 402', 40, '1200000']] as [$name, $local, $father, $phone, $share, $paid]) {
                CapitalService::record(\App\Models\Shareholder::create(['name' => $name, 'name_local' => $local, 'father_name' => $father, 'phone' => $phone, 'share_percent' => $share]), 'contribution', $day(64 + $years), $paid, $cash);
            }
            Vouchers::transfer($cash, $bank, $day(63 + $years), '2200000', 0, 'Deposit to the bank');
            DemoBulk::run($volume, $years, 62);
            $money = ['cash' => 800000, 'bank' => 2200000];

            $customers = [
                $party('customers', 'Sharif Super Store (Haji Mohammad Sharif)', '0700 418 226', 'Karte Naw, Kabul'),
                $party('customers', 'Popal Restaurant', '0799 705 331', 'Shahr-e Naw, Kabul'),
                $party('customers', 'Baran Bakery', '0786 902 147', 'Khair Khana, Kabul'),
                $party('customers', 'Noorullah Ahmadzai', '0773 156 890', 'Pul-e Charkhi, Kabul'),
                $party('customers', 'Sayed Mustafa Hashimi', '0744 320 665', 'Dasht-e Barchi, Kabul'),
                $party('customers', 'Mirwais Stanikzai Grocery', '0707 833 502', 'Charikar, Parwan'),
                $party('customers', 'Abdul Wahid Noorzai', '0795 471 038', 'Kote Sangi, Kabul'),
                $party('customers', 'Fawzia Rahimi Catering', '0728 664 915', 'Taimani, Kabul'),
            ];
            $suppliers = [
                $party('suppliers', 'Khan Brothers Wholesale', '0700 229 574', 'Mandawi, Kabul'),
                $party('suppliers', 'Mazar Flour Mill', '0799 388 120', 'Mazar-i-Sharif, Balkh'),
                $party('suppliers', 'Afghan Bazar Importers', '0788 140 763', 'Sarai Shamali, Kabul'),
                $party('suppliers', 'Kandahar Dry Fruits Co.', '0700 652 409', 'Kandahar'),
            ];

            // name, code, unit, sale price, cost, the supplier it comes from
            $items = [];
            foreach ([
                ['Flour 50 kg', 'FLR-50', 'bag', 1650, 1520, 1], ['Rice Sela 25 kg', 'RIC-25', 'bag', 2750, 2480, 0], ['Sugar 50 kg', 'SGR-50', 'bag', 3350, 3100, 0],
                ['Cooking oil 16 L', 'OIL-16', 'can', 1780, 1620, 2], ['Cooking oil 5 L', 'OIL-05', 'bottle', 590, 525, 2], ['Green tea 1 kg', 'TEA-G1', 'pack', 430, 360, 2],
                ['Black tea 500 g', 'TEA-B5', 'pack', 260, 215, 2], ['Chickpeas 50 kg', 'CHK-50', 'bag', 5200, 4750, 0], ['Red beans 50 kg', 'BNS-50', 'bag', 5600, 5150, 0],
                ['Tomato paste, carton of 12', 'TMP-12', 'carton', 1150, 990, 2], ['Raisins 1 kg', 'RSN-01', 'kg', 390, 310, 3], ['Almonds 1 kg', 'ALM-01', 'kg', 980, 830, 3],
                ['Salt 1 kg, carton of 20', 'SLT-20', 'carton', 320, 255, 0], ['Soap, carton of 72', 'SOP-72', 'carton', 1450, 1260, 2],
            ] as [$name, $code, $unit, $sale, $cost, $from]) {
                $items[] = ['item' => Item::create(['name' => $name, 'code' => $code, 'unit' => $unit, 'sale_price' => $sale, 'cost_price' => $cost, 'reorder_level' => 15]), 'sale' => $sale, 'cost' => $cost, 'from' => $from, 'stock' => 0];
            }
            $owed = array_fill(0, count($suppliers), 0);      // what the business owes each supplier
            $due = array_fill(0, count($customers), 0);       // what each customer owes the business

            // a delivery from one supplier: what is short is filled up; part is paid from the bank, the rest stays owed
            $buy = function (int $supplier, int $ago, int $upTo) use (&$items, &$owed, &$money, $suppliers, $afn, $bank, $day) {
                $lines = [];
                $total = 0;
                foreach ($items as $index => $row) {
                    if ($row['from'] === $supplier && $row['stock'] < $upTo / 2) {
                        $qty = $upTo - $row['stock'] + mt_rand(0, 3) * 5;
                        $lines[] = ['item_id' => $row['item']->id, 'qty' => $qty, 'price' => $row['cost']];
                        $items[$index]['stock'] += $qty;
                        $total += $qty * $row['cost'];
                    }
                }
                if (! $lines) {
                    return;
                }
                $paid = min($money['bank'] - 100000, (int) (round($total * (mt_rand(4, 7) / 10) / 1000) * 1000));
                $paid = max(0, $paid);
                // what it cost to bring the goods in: paid in cash, and carried by the goods
                $costs = [];
                foreach ([['Transport', 0.012], ['Loading and unloading', 0.004], ['Customs duty', $supplier === 2 ? 0.035 : 0]] as [$what, $share]) {
                    $amount = (int) (round($total * $share / 100) * 100);
                    if ($amount > 0 && $money['cash'] - $amount > 50000) {
                        $costs[] = ['name' => $what, 'amount' => $amount, 'account' => $cash];
                        $money['cash'] -= $amount;
                    }
                }
                Trade::record(Invoice::PURCHASE, $suppliers[$supplier], $afn, $day($ago), $lines, 0, $paid, $paid > 0 ? $bank : null, null, null, $costs);
                $money['bank'] -= $paid;
                $owed[$supplier] += $total - $paid;
            };
            // a sale of a few items that are in stock
            $sell = function (Party $to, int $ago, int $lines, int $most, float $paidShare, ?int $customer = null) use (&$items, &$due, &$money, $afn, $cash, $day) {
                $rows = [];
                $total = 0;
                $picked = (array) array_rand($items, min($lines, count($items)));
                foreach ($picked as $index) {
                    $qty = min(mt_rand(1, $most), $items[$index]['stock']);
                    if ($qty < 1) {
                        continue;
                    }
                    $rows[] = ['item_id' => $items[$index]['item']->id, 'qty' => $qty, 'price' => $items[$index]['sale']];
                    $items[$index]['stock'] -= $qty;
                    $total += $qty * $items[$index]['sale'];
                }
                if (! $rows) {
                    return;
                }
                $paid = $paidShare >= 1 ? $total : (int) (floor($total * $paidShare / 500) * 500);
                Trade::record(Invoice::SALE, $to, $afn, $day($ago), $rows, 0, $paid, $paid > 0 ? $cash : null, $paid < $total ? now()->subDays($ago)->addDays(30)->toDateString() : null);
                $money['cash'] += $paid;
                if ($customer !== null) {
                    $due[$customer] += $total - $paid;
                }
            };

            foreach (array_keys($suppliers) as $supplier) {
                $buy($supplier, 60, 60);
            }
            $walkIn = Trade::walkIn();
            for ($ago = 59; $ago >= 0; $ago--) {
                $date = now()->subDays($ago);
                if ($date->isFriday()) {
                    continue;                                   // the shop is closed on Fridays
                }
                // over the counter, paid in cash
                for ($n = mt_rand(1, 3); $n > 0; $n--) {
                    $sell($walkIn, $ago, mt_rand(1, 3), 4, 1);
                }
                // a shop or a restaurant takes a larger order and pays part of it
                if ($ago % 2 === 0) {
                    $who = mt_rand(0, count($customers) - 1);
                    $sell($customers[$who], $ago, mt_rand(2, 5), 12, [0, 0.3, 0.5, 1][mt_rand(0, 3)], $who);
                }
                // customers pay what they owe, in round sums
                if ($ago % 3 === 0) {
                    $who = array_search(max($due), $due);
                    $pays = (int) (floor($due[$who] * (mt_rand(4, 8) / 10) / 1000) * 1000);
                    if ($pays >= 2000) {
                        Vouchers::moneyIn($customers[$who], $cash, $day($ago), $pays, null, 'Paid on account');
                        $due[$who] -= $pays;
                        $money['cash'] += $pays;
                    }
                }
                // once a week the cash goes to the bank, a supplier is paid and the shelves are filled
                if ($date->isSaturday()) {
                    $deposit = (int) (floor(max(0, $money['cash'] - 150000) / 10000) * 10000);
                    if ($deposit > 0) {
                        Vouchers::transfer($cash, $bank, $day($ago), $deposit, 0, 'Deposit to the bank');
                        $money['cash'] -= $deposit;
                        $money['bank'] += $deposit;
                    }
                    $who = array_search(max($owed), $owed);
                    $pays = (int) (floor(min($owed[$who], $money['bank'] - 200000) * 0.7 / 1000) * 1000);
                    if ($pays >= 5000) {
                        Vouchers::moneyOut($suppliers[$who], $bank, $day($ago), $pays, 'Payment on account');
                        $owed[$who] -= $pays;
                        $money['bank'] -= $pays;
                    }
                    foreach (array_keys($suppliers) as $supplier) {
                        $buy($supplier, $ago, 50);
                    }
                }
                // the expenses of a shop
                $spend = function (string $key, int $amount, string $what) use (&$money, $main, $cash, $category, $day, $ago) {
                    if ($money['cash'] - $amount > 20000) {
                        ExpenseService::record($main, $category($key), $day($ago), $amount, $cash, $what);
                        $money['cash'] -= $amount;
                    }
                };
                if (in_array($ago, [58, 28], true)) {
                    $spend('expense_rent', 45000, 'Rent of the shop and the store room');
                    $spend('expense_staff', 62000, 'Salaries: two salesmen, a porter and a guard');
                    $spend('expense_bills', 6800, 'Electricity bill (Breshna)');
                    $spend('expense_telephone', 1500, 'Internet');
                }
                if ($ago % 6 === 1) {
                    $spend('expense_fuel', mt_rand(8, 16) * 250, 'Diesel for the delivery truck');
                }
                if ($ago % 9 === 4) {
                    $spend('expense_admin', mt_rand(3, 9) * 200, ['Invoice books and pens', 'Tea and sugar for the shop', 'Cleaning', 'Repair of the scale'][mt_rand(0, 3)]);
                }
                if ($ago % 11 === 5) {
                    $spend('expense_fees', mt_rand(4, 10) * 150, 'Porters and unloading');
                }
            }

            // a store room and a second shop: some of the goods are taken there
            $store = \App\Models\Warehouse::create(['name' => 'Store room, Sarai Haji Zaman', 'address' => 'Mandawi, Kabul']);
            $shop = \App\Models\Warehouse::create(['name' => 'Shop no. 2, Khair Khana', 'address' => 'Khair Khana, Kabul']);
            foreach ($items as $index => $row) {
                if ($row['stock'] >= 12) {
                    Trade::move($row['item'], \App\Models\Warehouse::main($main), $index % 2 ? $shop : $store, (int) floor($row['stock'] / 3), $day(1 + $index % 5), 'Sent to '.($index % 2 ? 'the second shop' : 'the store room'));
                }
            }

            // dollars: a customer leaves a deposit, part of it is sold, and some is kept with the saraf
            Vouchers::moneyIn($customers[1], $dollars, $day(34), '4000', null, 'Deposit for the wedding season orders');
            Vouchers::exchange($dollars, $cash, $day(33), '1500', '106050', 'Dollars sold at 70.70');
            Vouchers::transfer($dollars, $saraf, $day(20), '1500', 0, 'Left with the saraf for a hawala to Dubai');
            Vouchers::transfer($saraf, $dollars, $day(12), '500', 0, 'Taken back from the saraf');
            Vouchers::exchange($dollars, $cash, $day(4), '800', '56480', 'Dollars sold at 70.60');
            Vouchers::income($category('other_income'), $cash, $day(16), '7500', 'Delivery charge, Popal Restaurant');

            // the papers above were written today for days in the past: the owner is not told about each of them
            \App\Models\Alert::query()->delete();
        } finally {
            mt_srand();
            $before ? Auth::guard('web')->setUser($before) : Auth::guard('web')->forgetUser();
        }
    }
}
