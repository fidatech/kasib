<?php

namespace App\Services;

use App\Models\AccountCategory;
use App\Models\AccountGroup;
use App\Models\Currency;
use App\Models\Party;

/** The chart of accounts, the currencies and the account categories every business starts with. Safe to run again. */
class Chart
{
    /** key => [name, Dari name, nature] */
    public const CATEGORIES = [
        'cash' => ['Cash', 'پول نقد', 'asset'],
        'bank' => ['Bank accounts', 'حسابات بانکی', 'asset'],
        'saraf' => ['Accounts with sarafs', 'حسابات نزد صرافان', 'asset'],
        'receivable' => ['Accounts receivable', 'حسابات قابل حصول', 'asset'],
        'prepayment' => ['Prepayments', 'پیش پرداخت ها', 'asset'],
        'other_asset' => ['Other assets', 'سایر دارایی ها', 'asset'],
        'deal_goods' => ['Goods of open deals', 'اموال معاملات باز', 'asset'],
        'tangible_asset' => ['Tangible assets', 'دارایی های ملموس', 'asset'],
        'tangible_depreciation' => ['Depreciation of tangible assets', 'استهلاک دارایی های ملموس', 'asset'],
        'intangible_asset' => ['Intangible assets', 'دارایی های غیر ملموس', 'asset'],
        'intangible_amortisation' => ['Amortisation of intangible assets', 'استهلاک دارایی های غیر ملموس', 'asset'],

        // what a customer or a supplier left with the business; one who owes shows as a receivable on the balance sheet
        'customer_account' => ['Customer accounts', 'حسابات مشتریان', 'liability'],
        'supplier_account' => ['Supplier accounts', 'حسابات فروشندگان', 'liability'],
        'bills_payable' => ['Bills payable', 'بل های قابل پرداخت', 'liability'],
        'payable' => ['Accounts payable', 'حسابات قابل پرداخت', 'liability'],
        'tax_payable' => ['Tax payable', 'مالیات قابل پرداخت', 'liability'],
        'other_liability' => ['Other liabilities', 'سایر بدهی ها', 'liability'],

        'capital' => ["Partners' capital", 'سرمایه شرکاء', 'equity'],
        'opening_equity' => ['Opening balances', 'بیلانس های افتتاحیه', 'equity'],
        'retained_earnings' => ['Profit or loss of previous years', 'مفاد / ضرر سال های گذشته', 'equity'],

        'exchange_income' => ['Income from currency exchange', 'عواید تبادله اسعار', 'income'],
        'trade_income' => ['Profit from trade', 'مفاد تجارت', 'income'],
        'cash_excess' => ['Cash count excess', 'اضافی شمارش صندوق', 'income'],
        'other_income' => ['Other income', 'سایر عواید', 'income'],

        'expense_admin' => ['Administrative expenses', 'مصارف اداری', 'expense'],
        'expense_fuel' => ['Fuel', 'مصارف سوخت', 'expense'],
        'expense_telephone' => ['Telephone and internet', 'مصارف تیلیفون و انترنت', 'expense'],
        'expense_rent' => ['Rent', 'مصارف کرایه', 'expense'],
        'expense_fees' => ['Fees and commissions', 'فیس ها و کمیشن ها', 'expense'],
        'expense_staff' => ['Staff costs', 'مصارف کارمندان', 'expense'],
        'expense_marketing' => ['Marketing', 'مصارف بازاریابی', 'expense'],
        'expense_bills' => ['Payment of bills', 'پرداخت بل ها', 'expense'],
        'trade_loss' => ['Loss from trade', 'ضرر تجارت', 'expense'],
        'cash_shortage' => ['Cash count shortage', 'کمبود شمارش صندوق', 'expense'],
        'expense_other' => ['Other expenses', 'سایر مصارف', 'expense'],
        'depreciation' => ['Depreciation', 'استهلاک', 'expense'],
        'income_tax' => ['Tax', 'مالیه', 'expense'],
    ];

    /** code => [name, Dari name, symbol, quote unit, active from the start] */
    public const CURRENCIES = [
        'AFN' => ['Afghani', 'افغانی', '؋', 1, true],
        'USD' => ['US Dollar', 'دالر امریکایی', '$', 1, true],
        'EUR' => ['Euro', 'یورو', '€', 1, true],
        'PKR' => ['Pakistani Rupee', 'کلدار پاکستانی', '₨', 1000, true],
        'IRR' => ['Iranian Toman', 'تومان ایرانی', null, 1000, false],
        'AED' => ['UAE Dirham', 'درهم امارات', null, 1, false],
        'SAR' => ['Saudi Riyal', 'ریال سعودی', null, 1, false],
        'GBP' => ['British Pound', 'پوند انگلیس', '£', 1, false],
        'CNY' => ['Chinese Yuan', 'یوان چین', '¥', 1, false],
        'TRY' => ['Turkish Lira', 'لیره ترکیه', '₺', 1, false],
        'INR' => ['Indian Rupee', 'کلدار هندی', '₹', 100, false],
    ];

    /** key => [name, Dari, Pashto, type, icon, group of the chart] : the pages under Accounts every business starts with */
    public const GROUPS = [
        'cash' => ['Cash Accounts', 'حسابات نقدی', 'نغدي حسابونه', AccountGroup::MONEY, 'money-bill-wave', 'cash'],
        'bank' => ['Bank Accounts', 'حسابات بانکی', 'بانکي حسابونه', AccountGroup::MONEY, 'building-columns', 'bank'],
        'saraf' => ['Saraf Accounts', 'حسابات صرافان', 'د صرافانو حسابونه', AccountGroup::MONEY, 'coins', 'saraf'],
        'customers' => ['Customers', 'مشتریان', 'پیرودونکي', AccountGroup::PARTY, 'users', 'customer_account'],
        'suppliers' => ['Suppliers', 'فروشندگان', 'عرضه کوونکي', AccountGroup::PARTY, 'truck', 'supplier_account'],
    ];

    public static function seed(): void
    {
        $sort = 0;
        foreach (self::CATEGORIES as $key => [$name, $local, $nature]) {
            $sort += 10;
            // what a business changed (a name) is kept
            AccountCategory::firstOrCreate(['key' => $key], ['name' => $name, 'name_local' => $local, 'nature' => $nature, 'is_system' => true, 'sort' => $sort]);
        }

        $sort = 0;
        foreach (self::CURRENCIES as $code => [$name, $local, $symbol, $unit, $active]) {
            $sort += 10;
            Currency::firstOrCreate(['code' => $code], ['name' => $name, 'name_local' => $local, 'symbol' => $symbol, 'quote_unit' => $unit, 'is_base' => $code === 'AFN', 'is_active' => $active, 'sort' => $sort]);
        }

        $sort = 0;
        foreach (self::GROUPS as $key => [$name, $fa, $ps, $type, $icon, $category]) {
            $sort += 10;
            AccountGroup::firstOrCreate(['key' => $key], [
                'name' => $name, 'name_fa' => $fa, 'name_ps' => $ps, 'type' => $type, 'icon' => $icon,
                'account_category_id' => AccountCategory::byKey($category)->id, 'is_system' => true, 'sort' => $sort,
            ]);
        }

        // the cash box every business has
        $cash = AccountGroup::byKey('cash');
        if (! Party::where('account_group_id', $cash->id)->where('is_system', true)->exists()) {
            Party::create(['account_group_id' => $cash->id, 'name' => 'Cash', 'is_system' => true]);
        }
    }
}
