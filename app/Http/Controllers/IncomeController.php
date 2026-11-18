<?php

namespace App\Http\Controllers;

use App\Models\AccountCategory;
use App\Models\Voucher;
use App\Services\Vouchers;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Money earned that is not an exchange: the profit of a trade, a commission, a rent. */
class IncomeController extends VoucherController
{
    protected string $route = 'income';

    protected string $kind = Voucher::INCOME;

    protected string $title = 'Income';

    protected string $singular = 'Income';

    protected string $icon = 'hand-holding-dollar';

    protected ?string $hint = 'The profit of a trade and other income. A loss is recorded under Expenses.';

    protected function columns(): array
    {
        return [
            'No.' => fn (Voucher $v) => $v->number,
            'Date' => fn (Voucher $v) => fmt_date($v->date),
            'Income category' => fn (Voucher $v) => $v->otherAccount->category->label(),
            'Amount' => fn (Voucher $v) => money($v->amount, $v->currency->code),
            'Received into' => fn (Voucher $v) => $v->account->label(),
            'Details' => fn (Voucher $v) => $v->description,
            'User' => fn (Voucher $v) => $v->creator?->name,
        ];
    }

    protected function fields(): array
    {
        $categories = $this->incomeCategories();

        return [
            $this->dateField(),
            ['name' => 'account_category_id', 'label' => 'Income category', 'type' => 'select', 'col' => 5,
                'options' => $categories->mapWithKeys(fn (AccountCategory $c) => [$c->id => $c->label()])->all(), 'rules' => ['required', Rule::in($categories->pluck('id')->all())]],
            $this->moneyField('account_id', 'Received into', 'The amount is in the currency of this account.'),
            $this->amountField(),
            $this->detailsField(),
        ];
    }

    protected function record(array $data, Request $request): string
    {
        Vouchers::income(AccountCategory::findOrFail($data['account_category_id']), $this->money($data['account_id']), $data['date'], $data['amount'], $data['description'] ?? null);

        return $this->saved();
    }
}
