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
 * website and on its own sign-in page. It is filled with example papers and rebuilt every night,
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
            'licence_expires_on' => now()->addYears(5)->toDateString(), 'notes' => 'The demo of the website. Rebuilt every night by demo:refresh.'])->save();

        app(Tenancy::class)->run($tenant, fn () => self::fill());

        return $tenant;
    }

    /** A month of a small trading business: deposits, a loan, an exchange, expenses, purchases and sales. */
    private static function fill(): void
    {
        $before = Auth::guard('web')->user();
        Auth::guard('web')->setUser(User::orderBy('id')->firstOrFail());
        try {
            $main = Branch::main();
            $afn = Currency::base();
            $usd = Currency::where('code', 'USD')->firstOrFail();
            Rates::set($main, $usd, '70.50');
            foreach (['company_phone' => '0700 000 000', 'company_address' => 'Kabul'] as $key => $value) {
                Setting::updateOrCreate(['key' => $key], ['value' => $value]);
            }
            app()->forgetInstance('erp.settings');

            $cash = Accounts::cash($main, $afn);
            $dollars = Accounts::cash($main, $usd);
            $day = fn (int $ago) => now()->subDays($ago)->toDateString();
            $party = fn (string $group, string $name, string $phone) => Party::create(['account_group_id' => AccountGroup::byKey($group)->id, 'name' => $name, 'phone' => $phone])->fresh();

            $ahmad = $party('customers', 'Ahmad Karimi', '0700 111 222');
            $noor = $party('customers', 'Noor Trading', '0799 333 444');
            $sharif = $party('customers', 'Haji Sharif Store', '0788 555 666');
            $wholesale = $party('suppliers', 'Kabul Wholesale', '0700 777 888');
            $herat = $party('suppliers', 'Herat Foods', '0799 999 000');

            Vouchers::moneyIn($ahmad, $cash, $day(26), '450000', null, 'Deposit');
            Vouchers::moneyIn($noor, $dollars, $day(22), '5000', null, 'Deposit');
            Vouchers::moneyOut($sharif, $cash, $day(18), '40000', 'Loan');
            Vouchers::exchange($dollars, $cash, $day(14), '1000', '71000', 'Dollars sold');
            Vouchers::income(AccountCategory::byKey('other_income'), $cash, $day(12), '6500', 'Commission');
            ExpenseService::record($main, AccountCategory::byKey('expense_rent'), $day(11), '15000', $cash, 'Shop rent');
            ExpenseService::record($main, AccountCategory::byKey('expense_fuel'), $day(9), '2500', $cash, 'Generator fuel');
            ExpenseService::record($main, AccountCategory::byKey('expense_telephone'), $day(6), '1200', $cash, 'Internet');

            $item = fn (string $name, string $code, string $unit, string $sale, string $cost) => Item::create(['name' => $name, 'code' => $code, 'unit' => $unit, 'sale_price' => $sale, 'cost_price' => $cost, 'reorder_level' => 10]);
            $rice = $item('Rice 25 kg', 'RICE-25', 'bag', '2400', '2100');
            $oil = $item('Cooking oil 5 L', 'OIL-5', 'can', '650', '560');
            $sugar = $item('Sugar 50 kg', 'SUGAR-50', 'bag', '3300', '3000');
            $tea = $item('Green tea 1 kg', 'TEA-1', 'pack', '420', '350');

            Trade::record(Invoice::PURCHASE, $wholesale, $afn, $day(8), [['item_id' => $rice->id, 'qty' => '60', 'price' => '2100'], ['item_id' => $sugar->id, 'qty' => '30', 'price' => '3000']], 0, '150000', $cash);
            Trade::record(Invoice::PURCHASE, $herat, $afn, $day(7), [['item_id' => $oil->id, 'qty' => '100', 'price' => '560'], ['item_id' => $tea->id, 'qty' => '80', 'price' => '350']], 0, '84000', $cash);
            Trade::record(Invoice::SALE, $ahmad, $afn, $day(5), [['item_id' => $rice->id, 'qty' => '20', 'price' => '2400'], ['item_id' => $oil->id, 'qty' => '30', 'price' => '650']], 0, '67500', $cash);
            Trade::record(Invoice::SALE, $sharif, $afn, $day(2), [['item_id' => $sugar->id, 'qty' => '10', 'price' => '3300'], ['item_id' => $tea->id, 'qty' => '25', 'price' => '420']], 0, 0);
            Trade::record(Invoice::SALE, Trade::walkIn(), $afn, $day(0), [['item_id' => $rice->id, 'qty' => '3', 'price' => '2400']], 0, '7200', $cash);
            Vouchers::moneyIn($sharif, $cash, $day(0), '20000', null, 'Part of the loan paid back');
        } finally {
            $before ? Auth::guard('web')->setUser($before) : Auth::guard('web')->forgetUser();
        }
    }
}
