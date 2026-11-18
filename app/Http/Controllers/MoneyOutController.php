<?php

namespace App\Http\Controllers;

use App\Models\Party;
use App\Models\Voucher;
use App\Services\Vouchers;
use Illuminate\Http\Request;

/** Money the business pays to a customer, a supplier or another party. */
class MoneyOutController extends VoucherController
{
    protected string $route = 'money-out';

    protected string $kind = Voucher::MONEY_OUT;

    protected string $title = 'Money Out';

    protected string $singular = 'Money Out';

    protected string $icon = 'arrow-up';

    protected ?string $hint = 'Money paid to customers, suppliers and other parties.';

    protected function columns(): array
    {
        return [
            'No.' => fn (Voucher $v) => $v->number,
            'Date' => fn (Voucher $v) => fmt_date($v->date),
            'Account' => fn (Voucher $v) => $v->party?->name,
            'Amount' => fn (Voucher $v) => money($v->amount, $v->currency->code),
            'Paid from' => fn (Voucher $v) => $v->account->label(),
            'Details' => fn (Voucher $v) => $v->description,
            'User' => fn (Voucher $v) => $v->creator?->name,
        ];
    }

    protected function fields(): array
    {
        return [
            $this->dateField(),
            $this->partyField(),
            $this->moneyField('account_id', 'Paid from', 'The amount is in the currency of this account.'),
            $this->amountField(),
            $this->detailsField(),
        ];
    }

    protected function record(array $data, Request $request): string
    {
        Vouchers::moneyOut(Party::findOrFail($data['party_id']), $this->money($data['account_id']), $data['date'], $data['amount'], $data['description'] ?? null);

        return $this->saved();
    }
}
