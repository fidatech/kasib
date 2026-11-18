<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\FixedAsset;
use App\Services\Accounts;
use App\Services\AssetService;
use App\Support\Num;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class FixedAssetController extends PaperController
{
    protected string $route = 'assets';

    protected string $permission = 'assets';

    protected string $title = 'Fixed Assets';

    protected string $singular = 'Asset';

    protected string $icon = 'building';

    protected function rows(Request $request): LengthAwarePaginator
    {
        return FixedAsset::where('branch_id', viewed_branch()?->id)->orderByDesc('purchase_date')->orderByDesc('id')->paginate(per_page(25))->withQueryString();
    }

    protected function columns(): array
    {
        return [
            'Name' => fn (FixedAsset $a) => $a->name,
            'Kind' => fn (FixedAsset $a) => __(FixedAsset::KINDS[$a->kind] ?? $a->kind),
            'Bought on' => fn (FixedAsset $a) => fmt_date($a->purchase_date),
            'Cost (AFN)' => fn (FixedAsset $a) => money($a->cost),
            'Depreciated' => fn (FixedAsset $a) => money($a->depreciated),
            'Value now' => fn (FixedAsset $a) => money(Num::sub($a->cost, $a->depreciated)),
            'Every month' => fn (FixedAsset $a) => money(\App\Services\AssetService::monthly($a)),
            'Months recorded' => fn (FixedAsset $a) => \App\Services\AssetService::monthsDone($a).' / '.$a->useful_life_months,
            'Depreciation' => fn (FixedAsset $a) => $a->auto_depreciate ? __('Automatic') : __('By hand'),
        ];
    }

    protected function fields(): array
    {
        $branch = current_branch();
        $accounts = Accounts::money($branch, true)->concat(Accounts::liabilities($branch)->filter(fn (Account $a) => $a->currency->is_base));

        return [
            ['name' => 'name', 'label' => 'Name', 'rules' => ['required', 'string', 'max:150']],
            ['name' => 'kind', 'label' => 'Kind', 'type' => 'select', 'default' => 'tangible',
                'options' => ['tangible' => __('Tangible: furniture, computers, vehicles'), 'intangible' => __('Intangible: licence fee, software')], 'rules' => ['required', Rule::in(array_keys(FixedAsset::KINDS))]],
            ['name' => 'purchase_date', 'label' => 'Bought on', 'type' => 'date', 'default' => today_iso(), 'rules' => ['required', 'date']],
            ['name' => 'cost', 'label' => 'Cost (AFN)', 'type' => 'decimal', 'rules' => ['required', 'numeric', 'gt:0']],
            ['name' => 'useful_life_months', 'label' => 'Life in months', 'type' => 'number', 'default' => 60, 'rules' => ['required', 'integer', 'min:1', 'max:600'],
                'help' => 'The cost is spread evenly over these months'],
            ['name' => 'auto_depreciate', 'label' => 'Depreciation', 'type' => 'select', 'default' => '1',
                'options' => ['1' => __('Automatic: the system records it every month'), '0' => __('By hand: we record each month ourselves')],
                'rules' => ['nullable', Rule::in(['0', '1'])], 'help' => 'Automatic: when a month ends, its depreciation is recorded on the next working day, for every month of the life of the asset'],
            ['name' => 'salvage_value', 'label' => 'Value at the end of its life (AFN)', 'type' => 'decimal', 'default' => '0', 'rules' => ['nullable', 'numeric', 'min:0']],
            ['name' => 'paid_from_account_id', 'label' => 'Paid from', 'type' => 'select', 'default' => Accounts::cash($branch, \App\Models\Currency::base())->id,
                'options' => $accounts->mapWithKeys(fn (Account $a) => [$a->id => $a->label().' · '.$a->category->label()])->all(),
                'rules' => ['required', Rule::in($accounts->pluck('id')->all())], 'help' => 'Cash or bank, or a liability when it is not paid yet'],
            ['name' => 'notes', 'label' => 'Notes', 'col' => 12, 'rules' => ['nullable', 'string', 'max:500']],
        ];
    }

    protected function record(array $data, Request $request): string
    {
        AssetService::purchase(current_branch(), $data, Account::with('currency')->findOrFail($data['paid_from_account_id']));

        return __(':name saved.', ['name' => __($this->singular)]);
    }

    protected function find(int $id): Model
    {
        return FixedAsset::where('branch_id', current_branch()->id)->findOrFail($id);
    }

    protected function cancel(Model $paper, ?string $reason): void
    {
        AssetService::cancel($paper, $reason);
    }
}
