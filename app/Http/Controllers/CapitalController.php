<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Branch;
use App\Models\CapitalEntry;
use App\Models\Shareholder;
use App\Services\Accounts;
use App\Services\CapitalService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Capital paid in and drawings taken out by the shareholders. */
class CapitalController extends PaperController
{
    protected string $route = 'capital';

    protected string $permission = 'capital';

    protected string $title = 'Capital & Drawings';

    protected string $singular = 'Capital entry';

    protected string $icon = 'landmark';

    protected ?string $hint = 'Capital and drawings are in the base currency.';

    protected function mayRecord(): bool
    {
        return (bool) current_branch()?->isMain();
    }

    protected function rows(Request $request): LengthAwarePaginator
    {
        return CapitalEntry::with(['shareholder', 'branch', 'moneyAccount'])->orderByDesc('date')->orderByDesc('id')->paginate(per_page(25))->withQueryString();
    }

    protected function columns(): array
    {
        return [
            'Date' => fn (CapitalEntry $e) => fmt_date($e->date),
            'Shareholder' => fn (CapitalEntry $e) => $e->shareholder->label(),
            'Kind' => fn (CapitalEntry $e) => __(CapitalEntry::KINDS[$e->kind] ?? $e->kind),
            'Amount (AFN)' => fn (CapitalEntry $e) => money($e->amount),
            'Account' => fn (CapitalEntry $e) => $e->moneyAccount?->label(),
            'Notes' => fn (CapitalEntry $e) => $e->notes,
        ];
    }

    protected function fields(): array
    {
        $main = Branch::main();

        return [
            ['name' => 'shareholder_id', 'label' => 'Shareholder', 'type' => 'select',
                'options' => Shareholder::where('is_active', true)->orderBy('name')->get()->mapWithKeys(fn (Shareholder $s) => [$s->id => $s->label()])->all(),
                'rules' => ['required', Rule::exists('shareholders', 'id')]],
            ['name' => 'kind', 'label' => 'Kind', 'type' => 'select', 'default' => 'contribution',
                'options' => array_map(fn (string $text) => __($text), CapitalEntry::KINDS), 'rules' => ['required', Rule::in(array_keys(CapitalEntry::KINDS))]],
            ['name' => 'amount', 'label' => 'Amount (AFN)', 'type' => 'decimal', 'rules' => ['required', 'numeric', 'gt:0']],
            ['name' => 'money_account_id', 'label' => 'Cash or bank account', 'type' => 'select', 'default' => Accounts::cash($main, \App\Models\Currency::base())->id,
                'options' => Accounts::money($main, true)->mapWithKeys(fn (Account $a) => [$a->id => $a->label()])->all(),
                'rules' => ['required', Rule::exists('accounts', 'id')->where('branch_id', $main->id)]],
            ['name' => 'notes', 'label' => 'Notes', 'col' => 12, 'rules' => ['nullable', 'string', 'max:500']],
        ];
    }

    protected function record(array $data, Request $request): string
    {
        CapitalService::record(
            Shareholder::findOrFail($data['shareholder_id']),
            $data['kind'],
            today_iso(),
            $data['amount'],
            empty($data['money_account_id']) ? null : Account::with('currency')->findOrFail($data['money_account_id']),
            null,
            $data['notes'] ?? null,
        );

        return __(':name saved.', ['name' => __($this->singular)]);
    }

    protected function find(int $id): Model
    {
        return CapitalEntry::findOrFail($id);
    }

    protected function cancel(Model $paper, ?string $reason): void
    {
        CapitalService::cancel($paper, $reason);
    }
}
