<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\LiabilityEntry;
use App\Services\Accounts;
use App\Services\LiabilityService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class LiabilityController extends PaperController
{
    protected string $route = 'liabilities';

    protected string $permission = 'liabilities';

    protected string $title = 'Liabilities';

    protected string $singular = 'Liability entry';

    protected string $icon = 'scale-balanced';

    protected ?string $hint = 'Open the account of what is owed under Accounts first. An unpaid expense is recorded under Expenses.';

    protected function rows(Request $request): LengthAwarePaginator
    {
        return LiabilityEntry::with(['account.currency', 'moneyAccount'])->where('branch_id', viewed_branch()?->id)->orderByDesc('date')->orderByDesc('id')->paginate(per_page(25))->withQueryString();
    }

    protected function columns(): array
    {
        return [
            'Date' => fn (LiabilityEntry $e) => fmt_date($e->date),
            'Liability' => fn (LiabilityEntry $e) => $e->account->label(),
            'Kind' => fn (LiabilityEntry $e) => __(LiabilityEntry::KINDS[$e->kind] ?? $e->kind),
            'Amount' => fn (LiabilityEntry $e) => money($e->amount, $e->account->currency->code),
            'Account' => fn (LiabilityEntry $e) => $e->moneyAccount->label(),
            'Details' => fn (LiabilityEntry $e) => $e->description,
        ];
    }

    protected function fields(): array
    {
        $branch = current_branch();
        $liabilities = Accounts::liabilities($branch);
        $money = Accounts::money($branch);
        $option = fn (Account $a) => [$a->id => $a->label().' ('.$a->currency->code.')'];

        return [
            ['name' => 'account_id', 'label' => 'Liability', 'type' => 'select', 'options' => $liabilities->mapWithKeys($option)->all(), 'rules' => ['required', Rule::in($liabilities->pluck('id')->all())]],
            ['name' => 'kind', 'label' => 'Kind', 'type' => 'select', 'default' => 'payment',
                'options' => array_map(fn (string $text) => __($text), LiabilityEntry::KINDS), 'rules' => ['required', Rule::in(array_keys(LiabilityEntry::KINDS))]],
            ['name' => 'money_account_id', 'label' => 'Cash or bank account', 'type' => 'select', 'options' => $money->mapWithKeys($option)->all(),
                'rules' => ['required', Rule::in($money->pluck('id')->all())], 'help' => 'In the same currency as the liability'],
            ['name' => 'amount', 'label' => 'Amount', 'type' => 'decimal', 'rules' => ['required', 'numeric', 'gt:0']],
            ['name' => 'description', 'label' => 'Details', 'col' => 12, 'rules' => ['nullable', 'string', 'max:500']],
        ];
    }

    protected function record(array $data, Request $request): string
    {
        LiabilityService::record(Account::with(['currency', 'branch'])->findOrFail($data['account_id']), $data['kind'], today_iso(), $data['amount'], Account::with('currency')->findOrFail($data['money_account_id']), $data['description'] ?? null);

        return __(':name saved.', ['name' => __($this->singular)]);
    }

    protected function find(int $id): Model
    {
        return LiabilityEntry::where('branch_id', current_branch()->id)->findOrFail($id);
    }

    protected function cancel(Model $paper, ?string $reason): void
    {
        LiabilityService::cancel($paper, $reason);
    }
}
