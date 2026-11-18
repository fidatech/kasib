<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\AccountCategory;
use App\Models\Branch;
use App\Models\RecurringExpense;
use App\Services\Accounts;
use App\Services\OpeningBalances;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/** Expenses that come every month: rent, salaries, internet. Each is recorded by itself on its day. */
class RecurringExpenseController extends CrudController
{
    protected string $model = RecurringExpense::class;

    protected string $route = 'recurring-expenses';

    protected string $permission = 'expenses';

    protected string $title = 'Recurring Expenses';

    protected string $singular = 'Recurring expense';

    protected string $icon = 'rotate';

    protected array $search = ['description'];

    protected array $with = ['category', 'paidAccount.currency'];

    protected string $orderBy = 'day';

    protected function fields(?Model $record): array
    {
        $categories = AccountCategory::where('nature', 'expense')->where('key', '!=', 'depreciation')->orderBy('sort')->get();
        $accounts = Accounts::money(current_branch());

        return [
            ['name' => 'account_category_id', 'label' => 'Expense category', 'type' => 'select',
                'options' => $categories->mapWithKeys(fn (AccountCategory $c) => [$c->id => $c->label()])->all(), 'rules' => ['required', Rule::in($categories->pluck('id')->all())]],
            ['name' => 'paid_account_id', 'label' => 'Paid from', 'type' => 'select',
                'options' => $accounts->mapWithKeys(fn (Account $a) => [$a->id => $a->label().' · '.$a->currency->code])->all(), 'rules' => ['required', Rule::in($accounts->pluck('id')->all())],
                'help' => 'The amount is in the currency of this account.'],
            ['name' => 'amount', 'label' => 'Amount', 'type' => 'decimal', 'rules' => ['required', 'numeric', 'gt:0']],
            ['name' => 'day', 'label' => 'Day of the month', 'type' => 'number', 'default' => 1, 'rules' => ['required', 'integer', 'min:1', 'max:28'], 'help' => 'From 1 to 28. The expense is recorded on this day, every month.'],
            ['name' => 'description', 'label' => 'Details', 'col' => 12, 'rules' => ['nullable', 'string', 'max:500']],
            ['name' => 'is_active', 'label' => 'In use', 'type' => 'checkbox', 'default' => true],
        ];
    }

    protected function columns(): array
    {
        return [
            'Expense category' => fn (RecurringExpense $e) => $e->category->label(),
            'Details' => fn (RecurringExpense $e) => $e->description,
            'Amount' => fn (RecurringExpense $e) => money($e->amount, $e->paidAccount->currency->code),
            'Paid from' => fn (RecurringExpense $e) => $e->paidAccount->label(),
            'Day of the month' => fn (RecurringExpense $e) => $e->day,
            'Last recorded on' => fn (RecurringExpense $e) => fmt_date($e->last_posted_on),
            'In use' => fn (RecurringExpense $e) => $e->is_active ? __('Yes') : __('No'),
        ];
    }

    protected function shared(): array
    {
        return parent::shared() + ['headerForms' => [['label' => __('Record what is due now'), 'url' => route('recurring-expenses.run')]]];
    }

    /** Record now what would be recorded tonight. */
    public function run(): RedirectResponse
    {
        Gate::authorize('expenses.manage');
        $posted = OpeningBalances::runRecurring();

        return back()->with('success', __(':count recurring expenses recorded.', ['count' => $posted]));
    }
}
