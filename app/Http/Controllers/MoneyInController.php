<?php

namespace App\Http\Controllers;

use App\Models\Party;
use App\Models\Voucher;
use App\Services\Vouchers;
use Illuminate\Http\Request;

/** Money a customer, a supplier or another party hands to the business. */
class MoneyInController extends VoucherController
{
    protected string $route = 'money-in';

    protected string $kind = Voucher::MONEY_IN;

    protected string $title = 'Money In';

    protected string $singular = 'Money In';

    protected string $icon = 'arrow-down';

    protected ?string $hint = 'Money received from customers, suppliers and other parties.';

    protected function columns(): array
    {
        return [
            'No.' => fn (Voucher $v) => $v->number,
            'Date' => fn (Voucher $v) => fmt_date($v->date),
            'Account' => fn (Voucher $v) => $v->party?->name,
            'Amount' => fn (Voucher $v) => money($v->amount, $v->currency->code),
            'Received into' => fn (Voucher $v) => $v->account->label(),
            'To be paid back on' => fn (Voucher $v) => fmt_date($v->due_date),
            'Details' => fn (Voucher $v) => $v->description,
            'User' => fn (Voucher $v) => $v->creator?->name,
        ];
    }

    protected function fields(): array
    {
        return [
            $this->dateField(),
            $this->partyField(),
            $this->moneyField('account_id', 'Received into', 'The amount is in the currency of this account.'),
            $this->amountField(),
            ['name' => 'due_date', 'label' => 'To be paid back on', 'type' => 'date', 'col' => 4, 'rules' => ['nullable', 'date', 'after_or_equal:date'], 'help' => 'Leave empty when no day was agreed.'],
            $this->detailsField(),
        ];
    }

    protected function record(array $data, Request $request): string
    {
        Vouchers::moneyIn(Party::findOrFail($data['party_id']), $this->money($data['account_id']), $data['date'], $data['amount'], $data['due_date'] ?? null, $data['description'] ?? null);

        return $this->saved();
    }
}
