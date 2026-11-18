<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\AccountCategory;
use App\Models\Expense;
use App\Services\Accounts;
use App\Services\ExpenseService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ExpenseController extends PaperController
{
    protected string $route = 'expenses';

    protected string $permission = 'expenses';

    protected string $title = 'Expenses';

    protected string $singular = 'Expense';

    protected string $icon = 'receipt';

    protected function rows(Request $request): LengthAwarePaginator
    {
        return Expense::with(['category', 'paidAccount', 'currency', 'creator'])->where('branch_id', viewed_branch()?->id)->orderByDesc('date')->orderByDesc('id')->paginate(per_page(25))->withQueryString();
    }

    protected function columns(): array
    {
        return [
            'Date' => fn (Expense $e) => fmt_date($e->date),
            'Expense category' => fn (Expense $e) => $e->category->label(),
            'Details' => fn (Expense $e) => $e->description,
            'Amount' => fn (Expense $e) => money($e->amount, $e->currency->code),
            'Value in AFN' => fn (Expense $e) => money($e->base_amount),
            'Paid from' => fn (Expense $e) => $e->paidAccount->label(),
            'User' => fn (Expense $e) => $e->creator?->name,
        ];
    }

    /** Depreciation is recorded by its own screen, month by month. */
    private function categories()
    {
        return AccountCategory::where('nature', 'expense')->where('key', '!=', 'depreciation')->orderBy('sort')->get();
    }

    protected function fields(): array
    {
        $branch = current_branch();
        $accounts = Accounts::money($branch)->concat(Accounts::liabilities($branch));
        $categories = $this->categories();

        return [
            ['name' => 'account_category_id', 'label' => 'Expense category', 'type' => 'select',
                'options' => $categories->mapWithKeys(fn (AccountCategory $c) => [$c->id => $c->label()])->all(), 'rules' => ['required', Rule::in($categories->pluck('id')->all())]],
            ['name' => 'paid_account_id', 'label' => 'Paid from', 'type' => 'select', 'default' => Accounts::cash($branch, \App\Models\Currency::base())->id,
                'options' => $accounts->mapWithKeys(fn (Account $a) => [$a->id => $a->label().' ('.$a->currency->code.') · '.$a->category->label()])->all(),
                'rules' => ['required', Rule::in($accounts->pluck('id')->all())], 'help' => 'Cash or bank, or a liability when it is not paid yet. The amount is in the currency of this account.'],
            ['name' => 'amount', 'label' => 'Amount', 'type' => 'decimal', 'rules' => ['required', 'numeric', 'gt:0']],
            ['name' => 'description', 'label' => 'Details', 'col' => 12, 'rules' => ['nullable', 'string', 'max:500']],
        ];
    }

    protected function record(array $data, Request $request): string
    {
        ExpenseService::record(current_branch(), AccountCategory::findOrFail($data['account_category_id']), today_iso(), $data['amount'], Account::with('currency')->findOrFail($data['paid_account_id']), $data['description'] ?? null);

        return __(':name saved.', ['name' => __($this->singular)]);
    }

    protected function find(int $id): Model
    {
        return Expense::where('branch_id', current_branch()->id)->findOrFail($id);
    }

    protected function cancel(Model $paper, ?string $reason): void
    {
        ExpenseService::cancel($paper, $reason);
    }
}
