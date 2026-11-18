<?php

namespace App\Http\Controllers;

use App\Models\Currency;
use App\Models\RateAlert;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Rate alerts: the owner is told when a currency reaches a rate he is waiting for. */
class RateAlertController extends CrudController
{
    protected string $model = RateAlert::class;

    protected string $route = 'rate-alerts';

    protected string $permission = 'days';

    protected string $title = 'Rate Alerts';

    protected string $singular = 'Rate alert';

    protected string $icon = 'bell';

    protected array $search = ['note'];

    protected array $with = ['currency'];

    protected string $orderBy = 'id';

    protected string $orderDirection = 'desc';

    protected function fields(?Model $record): array
    {
        $currencies = Currency::where('is_active', true)->where('id', '!=', Currency::base()->id)->orderBy('code')->get();

        return [
            ['name' => 'currency_id', 'label' => 'Currency', 'type' => 'select', 'options' => $currencies->pluck('code', 'id')->all(), 'rules' => ['required', Rule::in($currencies->pluck('id')->all())]],
            ['name' => 'direction', 'label' => 'Tell me when the rate', 'type' => 'select', 'options' => array_map(fn ($label) => __($label), RateAlert::DIRECTIONS), 'rules' => ['required', Rule::in(array_keys(RateAlert::DIRECTIONS))]],
            ['name' => 'rate', 'label' => 'Rate', 'type' => 'decimal', 'rules' => ['required', 'numeric', 'gt:0'], 'help' => __('In :currency for one unit of the currency.', ['currency' => Currency::base()->code])],
            ['name' => 'note', 'label' => 'Note', 'rules' => ['nullable', 'string', 'max:255']],
            ['name' => 'is_active', 'label' => 'Waiting', 'type' => 'checkbox', 'default' => true],
        ];
    }

    protected function columns(): array
    {
        return [
            'Currency' => fn (RateAlert $a) => $a->currency->code,
            'Tell me when the rate' => fn (RateAlert $a) => __(RateAlert::DIRECTIONS[$a->direction] ?? $a->direction),
            'Rate' => fn (RateAlert $a) => \App\Support\Num::format($a->rate, 6, true),
            'Note' => fn (RateAlert $a) => $a->note,
            'Status' => fn (RateAlert $a) => $a->is_active ? __('Waiting') : ($a->fired_at ? __('Told on :date', ['date' => fmt_date($a->fired_at)]) : __('Switched off')),
        ];
    }

    protected function beforeSave(array $data, ?Model $record, Request $request): array
    {
        return $data + ($record ? [] : ['created_by' => $request->user()->id]);
    }
}
