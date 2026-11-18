<?php

namespace App\Services;

use App\Models\Account;
use App\Models\AccountCategory;
use App\Models\AccountGroup;
use App\Models\Branch;
use App\Models\Currency;
use App\Models\Party;
use App\Models\Shareholder;

/** The ledger accounts the system opens by itself, the first time each one is needed. */
class Accounts
{
    /** system key => [category key, name] */
    private const KEYS = [
        'exchange_income' => ['exchange_income', 'Income from currency exchange'],
        'other_income' => ['other_income', 'Other income'],
        'retained' => ['retained_earnings', 'Profit or loss of previous years'],
        'opening' => ['opening_equity', 'Opening balances'],
    ];

    /** The account the system keeps for a purpose. Income and capital accounts are in the base currency. */
    public static function system(Branch $branch, string $key, ?Currency $currency = null): Account
    {
        if ($key === 'cash') {
            return self::cash($branch, $currency ?? Currency::base());
        }
        [$category, $name] = self::KEYS[$key];

        return self::open($branch, $key, $category, $name, $currency);
    }

    /** Open (once) an account the system keeps for a purpose. */
    public static function open(Branch $branch, string $key, string $category, string $name, ?Currency $currency = null): Account
    {
        $currency ??= Currency::base();

        return Account::firstOrCreate(
            ['branch_id' => $branch->id, 'system_key' => $key, 'currency_id' => $currency->id],
            ['account_category_id' => AccountCategory::byKey($category)->id, 'name' => $name],
        );
    }

    /**
     * The ledger account of a party (a cash box, a bank account, a customer ...) in one currency.
     * It is opened the first time money in that currency touches the party.
     */
    public static function party(Party $party, Currency $currency, ?Branch $branch = null): Account
    {
        $branch ??= Branch::main();
        $party->loadMissing('group');

        $account = Account::firstOrCreate(
            ['branch_id' => $branch->id, 'system_key' => 'party.'.$party->id, 'currency_id' => $currency->id],
            ['account_category_id' => $party->group->account_category_id, 'party_id' => $party->id, 'name' => $party->name],
        );

        // a new one is read again, so it carries what the database filled in (it is in use)
        return $account->wasRecentlyCreated ? $account->fresh() : $account;
    }

    /** The cash box the business starts with. */
    public static function cashBox(): Party
    {
        return Party::where('account_group_id', AccountGroup::byKey('cash')->id)->where('is_system', true)->orderBy('id')->firstOrFail();
    }

    public static function cash(Branch $branch, Currency $currency): Account
    {
        return self::party(self::cashBox(), $currency, $branch);
    }

    /** A cash account for every active currency, so the cash of the business is always on the list. */
    public static function ensureCash(Branch $branch): void
    {
        $box = self::cashBox();
        foreach (Currency::active()->orderBy('sort')->get() as $currency) {
            self::party($box, $currency, $branch);
        }
    }

    public static function shareholder(Branch $main, Shareholder $shareholder): Account
    {
        return self::open($main, 'shareholder.'.$shareholder->id, 'capital', 'Capital of '.$shareholder->name);
    }

    /** The account that holds the cost of the assets of one kind (tangible or intangible). */
    public static function assets(Branch $branch, string $kind): Account
    {
        return $kind === 'intangible'
            ? self::open($branch, 'intangible_assets', 'intangible_asset', 'Intangible assets')
            : self::open($branch, 'tangible_assets', 'tangible_asset', 'Tangible assets');
    }

    public static function accumulated(Branch $branch, string $kind): Account
    {
        return $kind === 'intangible'
            ? self::open($branch, 'intangible_amortisation', 'intangible_amortisation', 'Amortisation of intangible assets')
            : self::open($branch, 'tangible_depreciation', 'tangible_depreciation', 'Depreciation of tangible assets');
    }

    public static function depreciationExpense(Branch $branch): Account
    {
        return self::open($branch, 'depreciation', 'depreciation', 'Depreciation');
    }

    /** Money accounts (cash, bank, saraf and any category of that type): where money is paid from and received into. */
    public static function money(Branch $branch, bool $baseOnly = false)
    {
        self::ensureCash($branch);

        return Account::active()->with(['currency', 'category', 'party'])
            ->where('branch_id', $branch->id)
            ->whereIn('account_category_id', AccountCategory::moneyIds())
            ->where(fn ($q) => $q->whereNull('party_id')->orWhereHas('party', fn ($p) => $p->where('is_active', true)))
            ->when($baseOnly, fn ($q) => $q->where('currency_id', Currency::base()->id))
            ->orderBy('account_category_id')->orderBy('name')->orderBy('currency_id')
            ->get()
            ->sortBy(fn (Account $a) => auth()->id() && (int) $a->party?->user_id === (int) auth()->id() ? 0 : 1)->values();
    }

    /** Accounts of what the business owes: bills, payables, tax and other liabilities. */
    public static function liabilities(Branch $branch)
    {
        return Account::active()->with(['currency', 'category'])
            ->where('branch_id', $branch->id)
            ->whereIn('account_category_id', AccountCategory::whereIn('key', ['bills_payable', 'payable', 'tax_payable', 'other_liability'])->pluck('id'))
            ->orderBy('account_category_id')->orderBy('name')
            ->get();
    }

    /** The account of one income or expense category (in the base currency). */
    public static function expense(Branch $branch, AccountCategory $category): Account
    {
        $base = Currency::base();

        return Account::firstOrCreate(
            ['branch_id' => $branch->id, 'system_key' => 'category.'.$category->id, 'currency_id' => $base->id],
            ['account_category_id' => $category->id, 'name' => $category->name, 'name_local' => $category->name_local],
        );
    }
}
