<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\PaperController;
use App\Models\Account;
use App\Models\Branch;
use App\Models\Distribution;
use App\Services\Accounts;
use App\Services\DistributionService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DistributionController extends PaperController
{
    protected string $route = 'distributions';

    protected string $permission = 'capital';

    protected string $title = 'Profit paid to partners';

    protected string $singular = 'Payment of profit';

    protected string $icon = 'hand-holding-dollar';

    protected ?string $hint = 'Shared by the percent of each partner. In the books of the main branch only.';

    protected function mayRecord(): bool
    {
        return current_branch()?->type === Branch::MAIN;
    }

    protected function rows(Request $request): LengthAwarePaginator
    {
        return Distribution::with(['paidAccount', 'creator'])->orderByDesc('id')->paginate(per_page(25))->withQueryString();
    }

    protected function columns(): array
    {
        return [
            'Number' => fn (Distribution $d) => $d->number,
            'Date' => fn (Distribution $d) => fmt_date($d->date),
            'Amount' => fn (Distribution $d) => money($d->amount),
            'Partners' => fn (Distribution $d) => collect($d->shares)->map(fn ($s) => $s['name'].' '.$s['percent'].'%: '.money($s['amount']))->implode(' · '),
            'Paid from' => fn (Distribution $d) => $d->paidAccount->label(),
            'User' => fn (Distribution $d) => $d->creator?->name,
        ];
    }

    protected function fields(): array
    {
        $accounts = Accounts::money(Branch::main(), true);

        return [
            ['name' => 'amount', 'label' => 'Amount', 'type' => 'decimal', 'rules' => ['required', 'numeric', 'gt:0']],
            ['name' => 'paid_account_id', 'label' => 'Paid from', 'type' => 'select', 'options' => $accounts->mapWithKeys(fn (Account $a) => [$a->id => $a->label()])->all(), 'rules' => ['required', Rule::in($accounts->pluck('id')->all())]],
            ['name' => 'note', 'label' => 'Details', 'col' => 12, 'rules' => ['nullable', 'string', 'max:500']],
        ];
    }

    protected function record(array $data, Request $request): string
    {
        $distribution = DistributionService::record($data['amount'], Account::with('currency')->findOrFail($data['paid_account_id']), $data['note'] ?? null);

        return __(':number saved.', ['number' => $distribution->number]);
    }

    protected function find(int $id): Model
    {
        return Distribution::findOrFail($id);
    }

    protected function cancel(Model $paper, ?string $reason): void
    {
        DistributionService::cancel($paper, $reason);
    }
}
