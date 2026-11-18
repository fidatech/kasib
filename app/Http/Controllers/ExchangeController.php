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
            $this->amountField('other_amount', 'Amount received', 'Leave it empty to have it worked out from the rate.', false),
            ['name' => 'rate', 'label' => 'Rate', 'type' => 'decimal', 'col' => 4, 'rules' => ['nullable', 'numeric', 'gt:0'],
                'help' => 'How much is received for one unit given. Empty: the average of the last five exchanges between these two currencies, or the rate of the business.'],
            $this->detailsField(),
        ];
    }

    /**
     * The rate an exchange between these two accounts usually has: the average of the last five
     * exchanges the same way, or, when there were none, the rate of the business.
     */
    public static function usualRate(\App\Models\Account $from, \App\Models\Account $to): string
    {
        $last = Voucher::where('kind', Voucher::EXCHANGE)->whereNull('cancelled_at')->where('currency_id', $from->currency_id)->where('other_currency_id', $to->currency_id)
            ->orderByDesc('id')->limit(5)->pluck('rate');
        if ($last->isNotEmpty()) {
            return Num::round(Num::div(Num::sum($last), $last->count()), 6);
        }

        return \App\Services\Rates::cross(current_branch(), $from->currency, $to->currency);
    }

    protected function record(array $data, Request $request): string
    {
        $from = $this->money($data['account_id']);
        $to = $this->money($data['other_account_id']);
        // the amount received: as typed, or worked out from the rate; the rate: as typed, or the usual one
        $received = $data['other_amount'] ?? null;
        if (! Num::isPositive($received ?? 0)) {
            $rate = Num::isPositive($data['rate'] ?? 0) ? $data['rate'] : self::usualRate($from, $to);
            $received = Num::money(Num::mul($data['amount'], $rate));
        }
        $voucher = Vouchers::exchange($from, $to, $data['date'], $data['amount'], $received, $data['description'] ?? null);

        // a deal far from the rates of the business is probably a typing mistake: it is saved, and pointed out
        $worth = \App\Services\Rates::toBase($voucher->amount, $voucher->currency, current_branch());
        if (Num::isPositive($worth) && abs((float) $voucher->profit) > 0.05 * (float) $worth) {
            session()->flash('warning', __('The rate of this exchange is more than 5% away from the rates of the business. Check the two amounts; cancel the paper if one was mistyped.'));
        }

        return $this->saved().' '.__('Profit of this deal: :amount', ['amount' => money($voucher->profit)]);
    }
}
