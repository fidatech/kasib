<?php

namespace App\Http\Controllers;

use App\Models\Voucher;
use App\Services\Vouchers;
use App\Support\Num;
use Illuminate\Http\Request;

/** One currency given, another received. The profit or loss of every deal is kept with it. */
class ExchangeController extends VoucherController
{
    protected string $route = 'exchanges';

    protected string $kind = Voucher::EXCHANGE;

    protected string $title = 'Exchanges';

    protected string $singular = 'Exchange';

    protected string $icon = 'arrow-right-arrow-left';

    protected ?string $hint = 'The profit of a deal is what was received above what was given, both at the rates of the business.';

    protected function columns(): array
    {
        return [
            'No.' => fn (Voucher $v) => $v->number,
            'Date' => fn (Voucher $v) => fmt_date($v->date),
            'Given' => fn (Voucher $v) => money($v->amount, $v->currency->code),
            'From' => fn (Voucher $v) => $v->account->label(),
            'Received' => fn (Voucher $v) => money($v->other_amount, $v->otherCurrency?->code),
            'Into' => fn (Voucher $v) => $v->otherAccount->label(),
            'Deal rate' => fn (Voucher $v) => Num::format($v->rate, 6, true),
            'Profit' => fn (Voucher $v) => money($v->profit),
            'User' => fn (Voucher $v) => $v->creator?->name,
        ];
    }

    protected function fields(): array
    {
        return [
            $this->dateField(),
            $this->moneyField('account_id', 'Given from', 'The account the money leaves.'),
            $this->amountField('amount', 'Amount given'),
            ['default' => null] + $this->moneyField('other_account_id', 'Received into', 'An account of the other currency.'),
            $this->amountField('other_amount', 'Amount received'),
            $this->detailsField(),
        ];
    }

    protected function record(array $data, Request $request): string
    {
        $voucher = Vouchers::exchange($this->money($data['account_id']), $this->money($data['other_account_id']), $data['date'], $data['amount'], $data['other_amount'], $data['description'] ?? null);

        return $this->saved().' '.__('Profit of this deal: :amount', ['amount' => money($voucher->profit)]);
    }
}
