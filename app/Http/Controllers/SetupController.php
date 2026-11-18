<?php

namespace App\Http\Controllers;

use App\Models\AccountCategory;
use App\Models\AccountGroup;
use App\Models\Currency;
use App\Models\JournalEntry;
use App\Models\Setting;
use App\Services\Accounts;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * The first steps of a new business: what kind of business it is, the currencies it works
 * with, its calendar. The kind of business adds the account categories and the income and
 * expense categories such a business usually needs; everything can be changed afterwards.
 */
class SetupController extends Controller
{
    /**
     * kind => [name, what it is for, account categories [key, name, Dari, Pashto, type, icon], expense categories [name, Dari], income categories [name, Dari]]
     */
    public const KINDS = [
        'exchange' => ['Money holding and exchange', 'Customers leave money with the business; it earns from exchange.', [], [], [['Commission income', 'عواید کمیشن']]],
        'trade' => ['Trade and import', 'Goods are bought and sold in deals; partners share in them.',
            [['partners', 'Partners', 'شرکا', 'شریکان', AccountGroup::PARTY, 'handshake']], [['Transport', 'مصارف ترانسپورت'], ['Customs', 'مصارف گمرک']], []],
        'shop' => ['Shop', 'A shop with daily cash, suppliers and a few employees.',
            [['employees', 'Employees', 'کارمندان', 'کارکوونکي', AccountGroup::PARTY, 'user-tie']], [['Shop supplies', 'لوازم دکان'], ['Electricity', 'مصارف برق']], [['Sales', 'فروشات']]],
        'services' => ['Services', 'An office that sells its work: income from services, salaries and rent.',
            [['employees', 'Employees', 'کارمندان', 'کارکوونکي', AccountGroup::PARTY, 'user-tie']], [['Electricity', 'مصارف برق']], [['Service income', 'عواید خدمات']]],
    ];

    public function show(): View
    {
        Gate::authorize('settings.manage');

        return view('setup.index', [
            'kinds' => self::KINDS, 'currencies' => Currency::orderBy('sort')->get(),
            'chosen' => setting('business_type'), 'done' => setting('setup_done') === '1',
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('settings.manage');
        $data = $request->validate([
            'business_type' => ['required', Rule::in(array_keys(self::KINDS))],
            'currencies' => ['nullable', 'array'],
            'currencies.*' => ['integer', Rule::exists('currencies', 'id')],
            'calendar' => ['required', Rule::in(['shamsi', 'gregorian'])],
            'simple_mode' => ['nullable', 'boolean'],
        ]);

        DB::transaction(function () use ($data, $request) {
            [, , $groups, $expenses, $incomes] = self::KINDS[$data['business_type']];
            foreach ($groups as [$key, $name, $fa, $ps, $type, $icon]) {
                if (AccountGroup::where('key', $key)->exists()) {
                    continue;
                }
                $nature = $type === AccountGroup::MONEY ? 'asset' : 'liability';
                $chart = AccountCategory::firstOrCreate(['key' => 'group_'.$key], ['name' => $name, 'name_local' => $fa, 'nature' => $nature, 'is_system' => false, 'sort' => (int) AccountCategory::where('nature', $nature)->max('sort') + 1]);
                AccountGroup::create(['key' => $key, 'name' => $name, 'name_fa' => $fa, 'name_ps' => $ps, 'type' => $type, 'icon' => $icon, 'account_category_id' => $chart->id, 'sort' => (int) AccountGroup::max('sort') + 10]);
            }
            foreach (['expense' => $expenses, 'income' => $incomes] as $nature => $list) {
                foreach ($list as [$name, $local]) {
                    if (! AccountCategory::where('nature', $nature)->where('name', $name)->exists()) {
                        AccountCategory::create(['key' => 'custom_'.$nature.'_'.(((int) AccountCategory::max('id')) + 1), 'name' => $name, 'name_local' => $local, 'nature' => $nature, 'is_system' => false, 'sort' => (int) AccountCategory::where('nature', $nature)->max('sort') + 1]);
                    }
                }
            }

            // the currencies the business works with; one that already has entries stays in use
            $wanted = array_map('intval', $data['currencies'] ?? []);
            foreach (Currency::all() as $currency) {
                $used = \App\Models\JournalLine::where('currency_id', $currency->id)->exists();
                $currency->update(['is_active' => $currency->is_base || $used || in_array((int) $currency->id, $wanted, true)]);
            }
            Accounts::ensureCash(current_branch());

            foreach (['business_type' => $data['business_type'], 'calendar' => $data['calendar'], 'simple_mode' => $request->boolean('simple_mode') ? '1' : '0', 'setup_done' => '1'] as $key => $value) {
                Setting::updateOrCreate(['key' => $key], ['value' => $value]);
            }
        });
        app()->forgetInstance('erp.settings');
        audit('updated', null, 'First setup: '.$data['business_type']);

        return redirect()->route('dashboard')->with('success', __('The system is ready. Add your accounts, or bring them in from Excel under Opening Balances.'));
    }
}
