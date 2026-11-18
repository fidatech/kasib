<?php

namespace App\Http\Controllers;

use App\Models\AccountCategory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Income and expense categories. Each one is a group of the chart of accounts, so what is
 * recorded under it lands on the profit and loss statement by itself.
 */
class ExpenseCategoryController extends CrudController
{
    /** Groups the system posts into by itself: they are not chosen by hand and cannot be removed. */
    private const KEPT = ['exchange_income', 'depreciation'];

    protected string $model = AccountCategory::class;

    protected string $route = 'expense-categories';

    protected string $permission = 'accounts';

    protected string $title = 'Income & Expense Categories';

    protected string $singular = 'Category';

    protected string $icon = 'tags';

    protected array $search = ['name', 'name_local'];

    protected string $orderBy = 'sort';

    protected function query(Request $request): Builder
    {
        return AccountCategory::query()->whereIn('nature', ['income', 'expense'])
            ->when($request->query('nature'), fn ($q, $nature) => $q->where('nature', $nature));
    }

    protected function filters(Request $request): array
    {
        return [['name' => 'nature', 'label' => __('Kind'), 'value' => $request->query('nature'), 'options' => ['income' => __('Income'), 'expense' => __('Expenses')]]];
    }

    protected function fields(?Model $record): array
    {
        $fields = [
            ['name' => 'name', 'label' => 'Name', 'rules' => ['required', 'string', 'max:150']],
            ['name' => 'name_local', 'label' => 'Name in Dari or Pashto', 'rules' => ['nullable', 'string', 'max:150']],
        ];
        // income or expense is chosen once: amounts already recorded cannot change their meaning
        if (! $record) {
            $fields[] = ['name' => 'nature', 'label' => 'Kind', 'type' => 'select', 'default' => 'expense',
                'options' => ['expense' => __('Expenses'), 'income' => __('Income')], 'rules' => ['required', Rule::in(['income', 'expense'])]];
        }

        return $fields;
    }

    protected function columns(): array
    {
        return [
            'Name' => fn (AccountCategory $c) => $c->label(),
            'Kind' => fn (AccountCategory $c) => $c->nature === 'income' ? __('Income') : __('Expenses'),
            'Added by' => fn (AccountCategory $c) => $c->is_system ? __('The system') : __('The business'),
        ];
    }

    protected function beforeSave(array $data, ?Model $record, Request $request): array
    {
        if (! $record) {
            $data['key'] = 'custom_'.$data['nature'].'_'.(((int) AccountCategory::max('id')) + 1);
            $data['is_system'] = false;
            $data['sort'] = (int) AccountCategory::where('nature', $data['nature'])->max('sort') + 1;
        }

        return $data;
    }

    protected function deleteProblem(Model $record): ?string
    {
        if ($record->is_system || in_array($record->key, self::KEPT, true)) {
            return __('A category of the system cannot be deleted.');
        }

        return $record->accounts()->exists() ? __('This category is already used, so it stays in the books.') : null;
    }
}
