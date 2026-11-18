<?php

namespace App\Services;

use App\Exceptions\UserError;
use App\Models\Branch;
use App\Models\Currency;
use App\Models\ExchangeRate;
use App\Support\Num;

/** Every branch writes its own rates; the newest one of a currency is the rate of that branch. */
class Rates
{
    /** How many AFN one quote unit of the currency is worth in this branch, or null when no rate was written yet. */
    public static function current(Branch $branch, Currency $currency): ?string
    {
        if ($currency->is_base) {
            return '1';
        }

        $rate = ExchangeRate::where('branch_id', $branch->id)->where('currency_id', $currency->id)->orderByDesc('id')->value('rate');

        return $rate === null ? null : Num::round($rate, 6);
    }

    public static function set(Branch $branch, Currency $currency, mixed $rate, mixed $buy = null, mixed $sell = null): ExchangeRate
    {
        if (! Num::isPositive($rate)) {
            throw new UserError(__('A rate must be more than zero.'));
        }

        $written = ExchangeRate::create([
            'branch_id' => $branch->id,
            'currency_id' => $currency->id,
            'rate' => Num::round($rate, 6),
            'buy_rate' => Num::isPositive($buy) ? Num::round($buy, 6) : null,
            'sell_rate' => Num::isPositive($sell) ? Num::round($sell, 6) : null,
            'created_by' => auth()->id(),
        ]);
        Alerts::rate($written, $currency);

        return $written;
    }

    /**
     * How many units of $to one unit of $from is worth in this branch, from the two AFN rates.
     * Nobody types a rate between two foreign currencies.
     */
    public static function cross(Branch $branch, Currency $from, Currency $to): string
    {
        $a = self::current($branch, $from);
        $b = self::current($branch, $to);
        if ($a === null || $b === null) {
            throw new UserError(__('There is no rate for :currency in this branch yet. Write today\'s rate first.', ['currency' => $a === null ? $from->code : $to->code]));
        }

        return Num::round(Num::div(Num::div($a, $from->quote_unit), Num::div($b, $to->quote_unit)), 6);
    }

    /** The value of an amount in AFN at the branch's rate. */
    public static function toBase(mixed $amount, Currency $currency, Branch $branch): string
    {
        if ($currency->is_base) {
            return Num::money($amount);
        }

        $rate = self::current($branch, $currency);
        if ($rate === null) {
            throw new UserError(__('There is no rate for :currency in this branch yet. Write today\'s rate first.', ['currency' => $currency->code]));
        }

        return Num::money(Num::div(Num::mul($amount, $rate), $currency->quote_unit));
    }
}
