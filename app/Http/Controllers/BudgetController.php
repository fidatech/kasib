<?php

namespace App\Http\Controllers;

use App\Models\AccountCategory;
use App\Models\Budget;
use App\Models\Currency;
use App\Services\Budgets;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

/** Expense budgets: a limit for a month on an expense category, with a warning at 80% and at 100%. */
class BudgetController extends CrudController
{
    protected string $model = Budget::class;

    protected string $route = 'budgets';

    protected string $permission = 'expenses';

    protected string $title = 'Expense Budgets';

    protected string $singular = 'Budget';

    protected string $icon = 'bullseye';

    protected array $with = ['category'];

    protected string $orderBy = 'id';

    protected function fields(?Model $record): array
    {
        $categories = AccountCategory::where('nature', 'expense')->where('key', '!=', 'depreciation')->orderBy('sort')->get();

        return [
            ['name' => 'account_category_id', 'label' => 'Expense category', 'type' => 'select', 'search' => true, 'col' => 12,
                'help' => __('One budget for each category. A category that already has one is edited from the list.'),
                'options' => $categories->mapWithKeys(fn (AccountCategory $c) => [$c->id => $c->label()])->all(),
                'rules' => ['required', Rule::in($categories->pluck('id')->all()), Rule::unique('budgets', 'account_category_id')->ignore($record?->id)]],
            ['name' => 'amount', 'label' => __('Budget for a month').' ('.Currency::base()->code.')', 'type' => 'decimal', 'col' => 12, 'rules' => ['required', 'numeric', 'gt:0'],
                'help' => __('The owner is warned when 80% is used, and again when it is all used.')],
        ];
    }

    protected function columns(): array
    {
        [$from, $to] = Budgets::month();
        $spent = fn (Budget $b) => Budgets::spent($b->account_category_id, $from, $to);

        return [
            'Expense category' => fn (Budget $b) => $b->category->label(),
            'Budget for a month' => fn (Budget $b) => money($b->amount),
            'Spent this month' => fn (Budget $b) => money($spent($b)),
            'Used' => fn (Budget $b) => Budgets::percent($spent($b), $b->amount).'%',
            'Left' => fn (Budget $b) => money(\App\Support\Num::sub($b->amount, $spent($b))),
        ];
    }
}
