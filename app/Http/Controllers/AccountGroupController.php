<?php

namespace App\Http\Controllers;

use App\Models\AccountCategory;
use App\Models\AccountGroup;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Account categories: Cash, Bank, Saraf, Customers, Suppliers and any the business adds.
 * A new category appears at once as a page of its own under Accounts in the menu, and gets
 * its own group in the chart of accounts, so the reports stay right without anyone thinking of it.
 */
class AccountGroupController extends CrudController
{
    /** Icons a category can have in the menu. */
    public const ICONS = [
        'address-book' => 'Address book', 'users' => 'People', 'user-tie' => 'Employee', 'handshake' => 'Partner', 'truck' => 'Supplier',
        'money-bill-wave' => 'Cash', 'building-columns' => 'Bank', 'coins' => 'Saraf', 'mobile-screen' => 'Mobile money', 'wallet' => 'Wallet',
        'store' => 'Shop', 'briefcase' => 'Business',
    ];

    protected string $model = AccountGroup::class;

    protected string $route = 'account-categories';

    protected string $permission = 'accounts';

    protected string $title = 'Account Categories';

    protected string $singular = 'Account category';

    protected string $icon = 'layer-group';

    protected array $search = ['name', 'name_fa', 'name_ps'];

    protected string $orderBy = 'sort';

    protected function fields(?Model $record): array
    {
        $fields = [
            ['name' => 'name', 'label' => 'Name', 'rules' => ['required', 'string', 'max:150']],
            ['name' => 'name_fa', 'label' => 'Name in Dari', 'rules' => ['nullable', 'string', 'max:150']],
            ['name' => 'name_ps', 'label' => 'Name in Pashto', 'rules' => ['nullable', 'string', 'max:150']],
        ];

        // the type is chosen once: amounts already recorded cannot change their meaning
        if (! $record) {
            $fields[] = ['name' => 'type', 'label' => 'Type', 'type' => 'select', 'default' => AccountGroup::PARTY,
                'options' => array_map(fn (string $text) => __($text), AccountGroup::TYPES), 'rules' => ['required', Rule::in(array_keys(AccountGroup::TYPES))],
                'help' => 'Money account: the funds of the business itself, listed where money is paid from and received into. Party account: someone the business owes or is owed by.'];
        }

        $fields[] = ['name' => 'icon', 'label' => 'Icon', 'type' => 'select', 'default' => 'address-book',
            'options' => array_map(fn (string $text) => __($text), self::ICONS), 'rules' => ['required', Rule::in(array_keys(self::ICONS))]];
        $fields[] = ['name' => 'sort', 'label' => 'Place in the menu', 'type' => 'number', 'default' => 100, 'rules' => ['required', 'integer', 'min:0', 'max:9999'], 'help' => 'A smaller number comes first.'];
        $fields[] = ['name' => 'is_active', 'label' => 'In use', 'type' => 'checkbox', 'default' => true, 'help' => 'A category that is switched off leaves the menu; its accounts and their entries stay.'];

        return $fields;
    }

    protected function columns(): array
    {
        return [
            'Name' => fn (AccountGroup $g) => $g->label(),
            'Type' => fn (AccountGroup $g) => __(AccountGroup::TYPES[$g->type] ?? $g->type),
            'Accounts' => fn (AccountGroup $g) => $g->parties()->count(),
            'Place in the menu' => fn (AccountGroup $g) => $g->sort,
            'In use' => fn (AccountGroup $g) => $g->is_active ? __('Yes') : __('No'),
        ];
    }

    protected function rowLinks(Model $record): array
    {
        return $record->is_active ? [['label' => __('Open'), 'url' => route('accounts.index', ['group' => $record->key])]] : [];
    }

    protected function beforeSave(array $data, ?Model $record, Request $request): array
    {
        if ($record) {
            return $data;
        }

        // the address of the page: letters of the name, or a number when the name has none
        $key = Str::slug($data['name']) ?: 'category';
        $taken = fn (string $k) => AccountGroup::where('key', $k)->exists() || in_array($k, ['create', 'statement'], true);
        for ($i = 2, $base = $key; $taken($key); $i++) {
            $key = $base.'-'.$i;
        }
        $data['key'] = $key;

        // its group in the chart of accounts: money is an asset; a party is shown as owed to (a liability) until it owes
        $data['account_category_id'] = AccountCategory::create([
            'key' => 'group_'.str_replace('-', '_', $key), 'name' => $data['name'], 'name_local' => $data['name_fa'] ?? $data['name_ps'] ?? null,
            'nature' => $data['type'] === AccountGroup::MONEY ? 'asset' : 'liability', 'is_system' => false,
            'sort' => (int) AccountCategory::where('nature', $data['type'] === AccountGroup::MONEY ? 'asset' : 'liability')->max('sort') + 1,
        ])->id;

        return $data;
    }

    protected function deleteProblem(Model $record): ?string
    {
        if ($record->is_system) {
            return __('The categories every business starts with cannot be deleted. Switch it off instead.');
        }

        return $record->parties()->exists() ? __('This category has accounts. Switch it off instead.') : null;
    }

    public function destroy(Request $request): \Illuminate\Http\RedirectResponse
    {
        $chart = AccountGroup::find($request->route('id'))?->account_category_id;
        $response = parent::destroy($request);

        // the group it had in the chart of accounts goes too, when nothing else uses it
        if ($chart && ! AccountGroup::where('account_category_id', $chart)->exists()) {
            AccountCategory::where('id', $chart)->where('is_system', false)->whereDoesntHave('accounts')->delete();
        }

        return $response;
    }
}
