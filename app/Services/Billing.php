<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\Central\CentralSetting;
use App\Models\Central\Invoice;
use App\Models\Central\Tenant;
use App\Support\Num;
use App\Tenancy\Tenancy;

/**
 * What a business pays Fida Technologies, in US dollars.
 * Two plans. Starter: 200 the first year (the system and its setup), then 100 a year.
 * Pro: 400 the first year, then 150 a year. Every further branch (from version 2): 100 the
 * first year, then 50 a year. The prices can be changed by the system administrator.
 * In the settings and on old invoices the Starter prices keep the keys main_first and main_renewal.
 */
class Billing
{
    public const DEFAULTS = ['main_first' => '200', 'main_renewal' => '100', 'pro_first' => '400', 'pro_renewal' => '150', 'branch_first' => '100', 'branch_renewal' => '50'];

    public const LABELS = [
        'main_first' => 'Starter plan, first year (the system and its setup)', 'main_renewal' => 'Starter plan for a year',
        'pro_first' => 'Pro plan, first year (the system and its setup)', 'pro_renewal' => 'Pro plan for a year',
        'branch_first' => 'A further branch, first year', 'branch_renewal' => 'A further branch, hosting for a year',
    ];

    /** plan => [name, key of its first-year price, key of its yearly price] */
    public const PLANS = ['starter' => ['Starter', 'main_first', 'main_renewal'], 'pro' => ['Pro', 'pro_first', 'pro_renewal']];

    /**
     * Pages that belong to the Pro plan besides version 2 (whose routes sit in the 'pro' group), by the
     * name of their route. A business on Starter does not see them in the menu and cannot open them.
     * Starter has one user, so the screens that manage users and roles are Pro too.
     */
    public const PRO_PAGES = [
        'deals.*', 'recurring-expenses.*', 'budgets.*',
        'assets.*', 'depreciation.*', 'liabilities.*', 'closing.*',
        'shareholders.*', 'capital.*', 'distributions.*',
        'reports.rates', 'reports.alerts', 'export.*', 'schedules.*',
        'users.*', 'roles.*', 'permissions.*',
        'documents.*',
    ];

    /** Whether a business may open the page of this route. */
    public static function allows(?Tenant $tenant, ?string $route): bool
    {
        return ! $tenant || ! $route || self::plan($tenant) === 'pro' || ! \Illuminate\Support\Str::is(self::PRO_PAGES, $route);
    }

    /** The plan a business is on. Anything that is not "pro" is the Starter plan. */
    public static function plan(Tenant $tenant): string
    {
        return $tenant->plan === 'pro' ? 'pro' : 'starter';
    }

    /** A line of an invoice that pays for a year of the system (and so moves the licence on). */
    public static function isYearly(?string $key): bool
    {
        return str_starts_with((string) $key, 'main_') || str_starts_with((string) $key, 'pro_');
    }

    /**
     * The plans as the website shows them.
     *
     * @return array<string, array{name: string, first: string, renewal: string, notes: list<string>}>
     */
    public static function plans(): array
    {
        $prices = self::prices();
        $out = [];
        foreach (self::PLANS as $key => [$name, $first, $renewal]) {
            $notes = array_values(array_filter(array_map('trim', preg_split('/\R/', (string) CentralSetting::read('plan_'.$key.'_notes')))));
            $out[$key] = ['name' => $name, 'first' => $prices[$first], 'renewal' => $prices[$renewal], 'notes' => $notes];
        }

        return $out;
    }

    /**
     * What a business owes: everything invoiced to it (cancelled invoices aside) less everything it paid.
     *
     * @return array{invoiced: string, paid: string, due: string}
     */
    public static function balance(Tenant $tenant): array
    {
        $invoiced = Num::money(Invoice::where('tenant_id', $tenant->id)->where('status', '!=', 'cancelled')->sum('total'));
        $paid = Num::money(\App\Models\Central\Payment::where('tenant_id', $tenant->id)->whereNull('cancelled_at')->sum('amount'));

        return ['invoiced' => $invoiced, 'paid' => $paid, 'due' => Num::money(Num::sub($invoiced, $paid))];
    }

    /** What was paid towards one invoice. */
    public static function paidOn(Invoice $invoice): string
    {
        return Num::money(\App\Models\Central\Payment::where('invoice_id', $invoice->id)->whereNull('cancelled_at')->sum('amount'));
    }

    /**
     * After a payment came or went: an invoice that is covered is paid (and a yearly one moves the
     * licence date), one that is no longer covered is not.
     */
    public static function settle(Invoice $invoice): void
    {
        if ($invoice->status === 'cancelled') {
            return;
        }
        $covered = Num::cmp(self::paidOn($invoice), $invoice->total) >= 0;
        $invoice->update(['status' => $covered ? 'paid' : 'unpaid', 'paid_on' => $covered ? ($invoice->paid_on ?: now()->toDateString()) : null]);
        $tenant = $invoice->tenant;
        $yearly = collect($invoice->lines ?? [['key' => 'main_renewal']])->contains(fn (array $line) => self::isYearly($line['key'] ?? ''));
        if ($covered && $yearly && (! $tenant->licence_expires_on || $tenant->licence_expires_on->lt($invoice->to_date))) {
            $tenant->update(['licence_expires_on' => $invoice->to_date]);
        }
        Licence::check($tenant);
    }

    /**
     * The invoice of the next year of a business: from the day its licence ends (or today, when
     * that day has passed) for one year. Used when a business pays from inside the system.
     */
    public static function invoiceNextYear(Tenant $tenant, ?string $note = null): Invoice
    {
        $quote = self::quote($tenant);
        $from = $tenant->licence_expires_on && $tenant->licence_expires_on->toDateString() > now()->toDateString() ? $tenant->licence_expires_on->copy() : now()->startOfDay();
        $main = collect($quote['lines'])->first(fn (array $line) => self::isYearly($line['key']));

        return \Illuminate\Support\Facades\DB::connection((new Invoice)->getConnectionName())->transaction(fn () => Invoice::create([
            'tenant_id' => $tenant->id, 'from_date' => $from->toDateString(), 'to_date' => $from->copy()->addYear()->toDateString(), 'note' => $note,
            'number' => sprintf('INV-%06d', ((int) Invoice::max('id')) + 1),
            'main_branch_price' => $main['amount'] ?? '0', 'branch_price' => self::prices()['branch_renewal'],
            'branches' => $quote['branches'], 'new_branches' => $quote['new_branches'], 'lines' => $quote['lines'], 'total' => $quote['total'],
        ]));
    }

    /** The free trial of the website: null when it is switched off, or the number of days a new business gets (7 until it is changed). */
    public static function trialDays(): ?int
    {
        return (CentralSetting::read('trial_enabled') ?? '1') === '1' ? max(1, min(90, (int) (CentralSetting::read('trial_days') ?: 7))) : null;
    }

    /** @return array{number: string, name: string, qr: bool, afn_rate: string} where a business pays */
    public static function hesabpay(): array
    {
        return ['number' => (string) CentralSetting::read('hesabpay_number'), 'name' => (string) CentralSetting::read('hesabpay_name'),
            'qr' => (bool) CentralSetting::read('hesabpay_qr'), 'afn_rate' => (string) CentralSetting::read('afn_rate')];
    }

    /** @return array<string, string> the prices */
    public static function prices(): array
    {
        $prices = [];
        foreach (self::DEFAULTS as $key => $default) {
            $value = CentralSetting::read('price_'.$key);
            $prices[$key] = Num::money($value !== null && is_numeric($value) ? $value : $default);
        }

        return $prices;
    }

    /**
     * What the next invoice of a business holds.
     * First year or renewal is read from its earlier invoices (cancelled ones do not count): the
     * main branch is new when there is none; a branch is new when no earlier invoice counted it.
     *
     * @param  bool  $onlyNew  an invoice in the middle of a year, for the branches added since the last one and nothing else
     * @return array{lines: list<array{key: string, label: string, quantity: int, price: string, amount: string}>, total: string, branches: int, new_branches: int, first: bool}
     */
    public static function quote(Tenant $tenant, bool $onlyNew = false): array
    {
        $prices = self::prices();
        // asked from inside the business itself, its database is open already
        $count = fn () => Branch::where('type', Branch::DEPENDENT)->where('is_active', true)->count();
        $branches = (int) ((int) app(Tenancy::class)->tenant?->id === (int) $tenant->id ? $count() : app(Tenancy::class)->run($tenant, $count));
        $earlier = Invoice::where('tenant_id', $tenant->id)->where('status', '!=', 'cancelled');
        $first = ! (clone $earlier)->exists();
        $billed = (int) (clone $earlier)->max('branches');
        $new = max(0, $branches - $billed);
        $old = $branches - $new;

        $lines = [];
        $add = function (string $key, int $quantity) use (&$lines, $prices) {
            if ($quantity > 0) {
                $lines[] = ['key' => $key, 'label' => self::LABELS[$key], 'quantity' => $quantity, 'price' => $prices[$key], 'amount' => Num::money(Num::mul($prices[$key], $quantity))];
            }
        };
        if (! $onlyNew) {
            [, $firstKey, $renewalKey] = self::PLANS[self::plan($tenant)];
            $add($first ? $firstKey : $renewalKey, 1);
            $add('branch_renewal', $old);
        }
        $add('branch_first', $new);

        return [
            'lines' => $lines, 'total' => Num::money(Num::sum(array_column($lines, 'amount'))),
            'branches' => $branches, 'new_branches' => $new, 'first' => $first,
        ];
    }
}
