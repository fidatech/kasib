<?php

namespace App\Http\Controllers;

use App\Models\DepreciationRun;
use App\Models\FixedAsset;
use App\Services\AssetService;
use App\Support\Num;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DepreciationController extends PaperController
{
    protected string $route = 'depreciation';

    protected string $permission = 'assets';

    protected string $title = 'Depreciation';

    protected string $singular = 'Depreciation of a month';

    protected string $icon = 'chart-line';

    protected ?string $hint = 'An asset loses the same amount every month of its life. Assets marked "automatic" are recorded by the system when a month ends; here a month is recorded by hand, for one asset or for all.';

    protected function rows(Request $request): LengthAwarePaginator
    {
        return DepreciationRun::with('asset')->withCount('lines')->where('branch_id', viewed_branch()?->id)->orderByDesc('period')->orderByDesc('id')->paginate(per_page(25))->withQueryString();
    }

    protected function columns(): array
    {
        return [
            'Month' => fn (DepreciationRun $r) => $r->period,
            'Asset' => fn (DepreciationRun $r) => $r->asset?->name ?? __('All assets').' ('.$r->lines_count.')',
            'Posted on' => fn (DepreciationRun $r) => fmt_date($r->date),
            'Amount (AFN)' => fn (DepreciationRun $r) => money($r->total),
            'Recorded by' => fn (DepreciationRun $r) => $r->is_auto ? __('Automatic') : __('By hand'),
        ];
    }

    /** The assets of the branch that still have value to lose, each with what it loses a month and how far it is. */
    private function assets(): \Illuminate\Support\Collection
    {
        return FixedAsset::where('branch_id', current_branch()->id)->whereNull('cancelled_at')->orderBy('name')->get()
            ->filter(fn (FixedAsset $a) => Num::isPositive(Num::sub(Num::sub($a->cost, $a->salvage_value), $a->depreciated)));
    }

    protected function fields(): array
    {
        $assets = $this->assets();
        $options = ['' => __('All assets of the branch')];
        foreach ($assets as $asset) {
            $options[$asset->id] = $asset->name.' · '.__(':amount AFN a month', ['amount' => money(AssetService::monthly($asset))])
                .' · '.__(':done of :life months', ['done' => AssetService::monthsDone($asset), 'life' => $asset->useful_life_months]);
        }

        return [
            ['name' => 'fixed_asset_id', 'label' => 'Asset', 'type' => 'select', 'options' => $options, 'search' => true, 'col' => 6,
                'rules' => ['nullable', Rule::in($assets->pluck('id')->all())], 'help' => 'One asset, or every asset of the branch that still has value to lose'],
            ['name' => 'period', 'label' => 'Month', 'default' => now()->format('Y-m'), 'rules' => ['required', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/'], 'help' => 'Year and month, for example 2026-11'],
        ];
    }

    protected function record(array $data, Request $request): string
    {
        $asset = empty($data['fixed_asset_id']) ? null : FixedAsset::findOrFail($data['fixed_asset_id']);
        $run = AssetService::depreciate(current_branch(), $data['period'], $asset);

        return __('Depreciation of :period recorded: :total AFN.', ['period' => $run->period, 'total' => money($run->total)]);
    }

    protected function find(int $id): Model
    {
        return DepreciationRun::where('branch_id', current_branch()->id)->findOrFail($id);
    }

    protected function cancel(Model $paper, ?string $reason): void
    {
        AssetService::cancelRun($paper, $reason);
    }
}
