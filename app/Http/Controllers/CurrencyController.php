<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Currency;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CurrencyController extends CrudController
{
    protected string $model = Currency::class;

    protected string $route = 'currencies';

    protected string $permission = 'currencies';

    protected string $title = 'Currencies';

    protected string $singular = 'Currency';

    protected string $icon = 'coins';

    protected array $search = ['code', 'name', 'name_local'];

    protected string $orderBy = 'sort';

    protected function fields(?Model $record): array
    {
        $fields = [
            // the code is on every account of the currency, so it is written once
            ['name' => 'code', 'label' => 'Code', 'readonly' => (bool) $record, 'rules' => ['required', 'alpha', 'size:3', Rule::unique('currencies')->ignore($record?->id)], 'help' => 'Three letters, for example USD'],
            ['name' => 'name', 'label' => 'Name', 'rules' => ['required', 'string', 'max:100']],
            ['name' => 'name_local', 'label' => 'Name in Dari or Pashto', 'rules' => ['nullable', 'string', 'max:100']],
            ['name' => 'symbol', 'label' => 'Symbol', 'rules' => ['nullable', 'string', 'max:10']],
            ['name' => 'quote_unit', 'label' => 'Rates are written for', 'type' => 'select', 'default' => 1,
                'options' => [1 => __('1 unit'), 100 => __('100 units'), 1000 => __('1,000 units')], 'rules' => ['required', Rule::in([1, 100, 1000])],
                'help' => 'The way the market says the rate, for example 1,000 Pakistani rupees'],
        ];
        if (! $record?->is_base) {
            $fields[] = ['name' => 'is_active', 'label' => 'In use', 'type' => 'checkbox', 'default' => true];
        }

        return $fields;
    }

    protected function columns(): array
    {
        return [
            'Code' => fn (Currency $c) => $c->code,
            'Name' => fn (Currency $c) => $c->name.($c->is_base ? ' · '.__('main currency') : ''),
            'Name in Dari or Pashto' => fn (Currency $c) => $c->name_local,
            'Rates are written for' => fn (Currency $c) => number_format($c->quote_unit),
            'In use' => fn (Currency $c) => $c->is_active ? __('Yes') : __('No'),
        ];
    }

    protected function save(Request $request, ?Model $record): Model
    {
        $request->merge(['code' => strtoupper(trim((string) $request->input('code')))]);

        return parent::save($request, $record);
    }

    /** A currency in use gets its cash account in every branch that keeps books, by itself. */
    protected function afterSave(Model $record, array $virtual, Request $request, bool $created): void
    {
        if ($record->is_active) {
            foreach (\App\Models\Branch::withBooks()->get() as $branch) {
                \App\Services\Accounts::cash($branch, $record);
            }
        }
    }

    protected function deleteProblem(Model $record): ?string
    {
        if ($record->is_base) {
            return __('The main currency cannot be deleted.');
        }
        if (Account::where('currency_id', $record->id)->exists()) {
            return __('This currency has accounts. Switch it off instead.');
        }

        return null;
    }
}
