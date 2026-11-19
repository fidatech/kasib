<?php

namespace App\Services;

use App\Exceptions\UserError;
use App\Models\Account;
use App\Models\Branch;
use App\Models\DepreciationLine;
use App\Models\DepreciationRun;
use App\Models\FixedAsset;
use App\Support\Num;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/** Fixed assets of a branch and their straight-line depreciation, month by month. In AFN. */
class AssetService
{
    public static function purchase(Branch $branch, array $data, Account $paidFrom): FixedAsset
    {
        $cost = Num::money($data['cost']);
        $salvage = Num::money($data['salvage_value'] ?? 0);
        if (! Num::isPositive($cost)) {
            throw new UserError(__('The amount must be more than zero.'));
        }
        if (Num::isNegative($salvage) || Num::cmp($salvage, $cost) >= 0) {
            throw new UserError(__('The value at the end of its life must be less than the cost.'));
        }
        if ((int) $paidFrom->branch_id !== (int) $branch->id || ! $paidFrom->currency->is_base) {
            throw new UserError(__('Choose an account of your branch in AFN.'));
        }

        return DB::transaction(function () use ($branch, $data, $paidFrom, $cost, $salvage) {
            $asset = FixedAsset::create([
                'branch_id' => $branch->id, 'name' => $data['name'], 'kind' => $data['kind'], 'purchase_date' => $data['purchase_date'],
                'cost' => $cost, 'salvage_value' => $salvage, 'useful_life_months' => (int) $data['useful_life_months'],
                'auto_depreciate' => (string) ($data['auto_depreciate'] ?? '1') === '1',
                'paid_from_account_id' => $paidFrom->id, 'notes' => $data['notes'] ?? null, 'created_by' => auth()->id(),
            ]);

            Ledger::post($branch, 'asset', today_iso(), __('Asset bought').': '.$asset->name, [
                ['account' => Accounts::assets($branch, $asset->kind), 'debit' => $cost],
                ['account' => $paidFrom, 'credit' => $cost],
            ], $asset);
            audit('created', $asset, 'Asset '.$asset->name.' '.$cost);

            return $asset;
        });
    }

    public static function cancel(FixedAsset $asset, ?string $reason = null): void
    {
        if ($asset->isCancelled()) {
            return;
        }
        if (Num::isPositive($asset->depreciated)) {
            throw new UserError(__('This asset already has depreciation. Cancel the depreciation of those months first.'));
        }

        DB::transaction(function () use ($asset, $reason) {
            $asset->update(['cancelled_at' => now()]);
            Ledger::voidSource($asset, $reason);
        });
    }

    /** What an asset loses every month: its cost less its value at the end, spread over the months of its life. */
    public static function monthly(FixedAsset $asset): string
    {
        return Num::money(Num::div(Num::sub($asset->cost, $asset->salvage_value), max(1, $asset->useful_life_months)));
    }

    /** How many months of its life are recorded already. */
    public static function monthsDone(FixedAsset $asset): int
    {
        return DepreciationLine::where('fixed_asset_id', $asset->id)->whereHas('run', fn ($q) => $q->whereNull('cancelled_at'))->count();
    }

    /**
     * Depreciation of one month ("2026-11"): of one asset, or of every asset of the branch that
     * still has value to lose. An asset is depreciated once for a month, never twice.
     *
     * @param  bool  $auto  recorded by the system (assets marked for it only), not by a user
     */
    public static function depreciate(Branch $branch, string $period, ?FixedAsset $only = null, bool $auto = false): DepreciationRun
    {
        if (! preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $period)) {
            throw new UserError(__('Write the month as year and month, for example 2026-11.'));
        }
        $end = Carbon::createFromFormat('Y-m-d', $period.'-01')->endOfMonth()->toDateString();
        if ($only && ((int) $only->branch_id !== (int) $branch->id || $only->isCancelled())) {
            throw new UserError(__('Choose an asset of your branch.'));
        }

        return DB::transaction(function () use ($branch, $period, $end, $only, $auto) {
            $assets = FixedAsset::where('branch_id', $branch->id)->whereNull('cancelled_at')->whereDayOf('purchase_date', '<=', $end)
                ->when($only, fn ($q) => $q->whereKey($only->id))
                ->when($auto, fn ($q) => $q->where('auto_depreciate', true))
                ->orderBy('id')->lockForUpdate()->get();
            // an asset that already has this month is left out
            $done = DepreciationLine::whereIn('fixed_asset_id', $assets->pluck('id'))
                ->whereHas('run', fn ($q) => $q->where('period', $period)->when(! $auto, fn ($i) => $i->whereNull('cancelled_at')))
                ->pluck('fixed_asset_id')->all();

            $parts = [];
            $totals = ['tangible' => '0', 'intangible' => '0'];
            foreach ($assets as $asset) {
                if (in_array($asset->id, $done, false)) {
                    continue;
                }
                $base = Num::sub($asset->cost, $asset->salvage_value);
                $left = Num::money(Num::sub($base, $asset->depreciated));
                if (! Num::isPositive($left)) {
                    continue;
                }
                $monthly = self::monthly($asset);
                // the last month of its life takes what is left, so the total is exact
                $last = self::monthsDone($asset) + 1 >= $asset->useful_life_months || Num::cmp($monthly, $left) >= 0 || ! Num::isPositive($monthly);
                $amount = $last ? $left : $monthly;
                $parts[] = [$asset, $amount];
                $totals[$asset->kind] = Num::add($totals[$asset->kind], $amount);
            }

            $total = Num::money(Num::add($totals['tangible'], $totals['intangible']));
            if (! $parts || ! Num::isPositive($total)) {
                throw new UserError($done
                    ? __('Depreciation of :period is already recorded.', ['period' => $period])
                    : __('There is nothing to depreciate in :period.', ['period' => $period]));
            }

            $run = DepreciationRun::create([
                'branch_id' => $branch->id, 'fixed_asset_id' => $only?->id, 'period' => $period, 'date' => today_iso(),
                'total' => $total, 'is_auto' => $auto, 'created_by' => auth()->id(),
            ]);
            foreach ($parts as [$asset, $amount]) {
                DepreciationLine::create(['depreciation_run_id' => $run->id, 'fixed_asset_id' => $asset->id, 'amount' => $amount]);
                $asset->update(['depreciated' => Num::money(Num::add($asset->depreciated, $amount))]);
            }

            Ledger::post($branch, 'depreciation', today_iso(), __('Depreciation').' '.$period.($only ? ': '.$only->name : ''), array_values(array_filter([
                ['account' => Accounts::depreciationExpense($branch), 'debit' => $total],
                Num::isPositive($totals['tangible']) ? ['account' => Accounts::accumulated($branch, 'tangible'), 'credit' => Num::money($totals['tangible'])] : null,
                Num::isPositive($totals['intangible']) ? ['account' => Accounts::accumulated($branch, 'intangible'), 'credit' => Num::money($totals['intangible'])] : null,
            ])), $run);
            audit('created', $run, 'Depreciation '.$period.' '.$total.($auto ? ' (automatic)' : ''));

            return $run;
        });
    }

    /**
     * Automatic depreciation: for the assets marked for it, record every finished month that is
     * still missing, from the month they were bought. Called when the branch opens its day, so a
     * month is recorded on the first working day after it ends. A month whose paper a user
     * cancelled is not recorded again by the system.
     *
     * @return int how many months were recorded
     */
    public static function catchUp(Branch $branch): int
    {
        $assets = FixedAsset::where('branch_id', $branch->id)->whereNull('cancelled_at')->where('auto_depreciate', true)->get()
            ->filter(fn (FixedAsset $a) => Num::isPositive(Num::sub(Num::sub($a->cost, $a->salvage_value), $a->depreciated)));
        if ($assets->isEmpty()) {
            return 0;
        }
        $month = Carbon::parse($assets->min(fn (FixedAsset $a) => $a->purchase_date->toDateString()))->startOfMonth();
        $thisMonth = Carbon::parse(today_iso())->startOfMonth();
        $recorded = 0;
        // never more than 50 years of months, whatever date was typed
        for ($i = 0; $i < 600 && $month->lt($thisMonth); $i++, $month->addMonth()) {
            try {
                self::depreciate($branch, $month->format('Y-m'), null, true);
                $recorded++;
            } catch (UserError) {
                // nothing left to record for this month
            }
        }

        return $recorded;
    }

    public static function cancelRun(DepreciationRun $run, ?string $reason = null): void
    {
        if ($run->isCancelled()) {
            return;
        }

        DB::transaction(function () use ($run, $reason) {
            foreach ($run->lines()->with('asset')->get() as $line) {
                $line->asset->update(['depreciated' => Num::money(Num::sub($line->asset->depreciated, $line->amount))]);
            }
            $run->update(['cancelled_at' => now()]);
            Ledger::voidSource($run, $reason);
        });
    }
}
