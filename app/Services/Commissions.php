<?php

namespace App\Services;

use App\Models\Central\CentralSetting;
use App\Models\Central\Commission;
use App\Models\Central\Payment;
use App\Models\Central\Seller;
use App\Models\Central\Tenant;
use App\Support\Num;

/**
 * Seller commissions and client referrals. When a business pays, its seller earns his share of
 * the payment (one rate for the first year, another for renewals). The first time a referred
 * business pays, the client that referred it earns the reward written in the settings. The
 * company pays these out by hand and marks them paid.
 */
class Commissions
{
    public static function forPayment(Payment $payment): void
    {
        $tenant = $payment->tenant;
        if (! $tenant || Commission::where('payment_id', $payment->id)->exists()) {
            return;
        }
        $first = collect($payment->invoice?->lines ?? [])->contains(fn (array $line) => str_ends_with((string) ($line['key'] ?? ''), '_first'));
        $seller = $tenant->seller_id ? Seller::find($tenant->seller_id) : null;
        if ($seller && $seller->is_active) {
            $percent = $first ? $seller->first_percent : $seller->renewal_percent;
            if (Num::isPositive($percent)) {
                Commission::create(['kind' => 'seller', 'seller_id' => $seller->id, 'tenant_id' => $tenant->id, 'payment_id' => $payment->id, 'percent' => $percent,
                    'amount' => Num::money(Num::div(Num::mul($payment->amount, $percent), '100'))]);
            }
        }
        // a referral is rewarded once, on the first payment of the business that was referred
        $reward = CentralSetting::read('referral_reward');
        if ($tenant->referred_by && Num::isPositive($reward ?: 0) && ! Commission::where('kind', 'referral')->where('tenant_id', $tenant->id)->whereNull('cancelled_at')->exists()) {
            Commission::create(['kind' => 'referral', 'referrer_id' => $tenant->referred_by, 'tenant_id' => $tenant->id, 'payment_id' => $payment->id, 'amount' => Num::money($reward)]);
        }
    }

    /** A payment was cancelled: what was not paid out yet is cancelled with it. */
    public static function cancelFor(Payment $payment): void
    {
        Commission::where('payment_id', $payment->id)->whereNull('paid_on')->whereNull('cancelled_at')->update(['cancelled_at' => now()]);
    }

    /** "?ref=" of the registration form: the code of a seller, or the address of a client. */
    public static function attach(Tenant $tenant, ?string $ref): void
    {
        $ref = strtolower(trim((string) $ref));
        if ($ref === '') {
            return;
        }
        if ($seller = Seller::where('code', $ref)->where('is_active', true)->first()) {
            $tenant->forceFill(['seller_id' => $seller->id])->save();
        } elseif ($referrer = Tenant::where('slug', $ref)->where('id', '!=', $tenant->id)->first()) {
            $tenant->forceFill(['referred_by' => $referrer->id])->save();
        }
    }
}
