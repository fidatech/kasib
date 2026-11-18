<?php

namespace App\Http\Controllers;

use App\Models\Voucher;
use App\Services\Vouchers;
use Illuminate\Http\Request;

/** Money moved between two money accounts of the same currency. */
class TransferController extends VoucherController
{
    protected string $route = 'transfers';

    protected string $kind = Voucher::TRANSFER;

    protected string $title = 'Transfers';

    protected string $singular = 'Transfer';

    protected string $icon = 'right-left';

    protected ?string $hint = 'Between cash, bank and saraf accounts of the same currency.';

    protected function columns(): array
    {
        return [
            'No.' => fn (Voucher $v) => $v->number,
            'Date' => fn (Voucher $v) => fmt_date($v->date),
            'From' => fn (Voucher $v) => $v->account->label(),
            'To' => fn (Voucher $v) => $v->otherAccount->label(),
            'Amount' => fn (Voucher $v) => money($v->amount, $v->currency->code),
            'Fee' => fn (Voucher $v) => \App\Support\Num::isZero($v->fee) ? '' : money($v->fee, $v->currency->code),
            'Details' => fn (Voucher $v) => $v->description,
            'User' => fn (Voucher $v) => $v->creator?->name,
        ];
    }

    protected function fields(): array
    {
        return [
            $this->dateField(),
            $this->moneyField('account_id', 'From'),
            ['default' => null] + $this->moneyField('other_account_id', 'To', 'An account of the same currency.'),
            $this->amountField(),
            $this->amountField('fee', 'Fee', 'What the bank or the saraf took, if anything. It is paid from the first account.', false),
            $this->detailsField(),
        ];
    }

    protected function record(array $data, Request $request): string
    {
        Vouchers::transfer($this->money($data['account_id']), $this->money($data['other_account_id']), $data['date'], $data['amount'], $data['fee'] ?? 0, $data['description'] ?? null);

        return $this->saved();
    }
}
