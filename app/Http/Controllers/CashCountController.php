<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\AccountCategory;
use App\Models\Branch;
use App\Models\CashCount;
use App\Services\Accounts;
use App\Services\Deals;
use App\Services\Ledger;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** The cash counted by hand at the end of the day, set against the books. */
class CashCountController extends PaperController
{
    protected string $route = 'cash-counts';

    protected string $permission = 'transactions';

    protected string $title = 'Daily Cash Count';

    protected string $singular = 'Cash count';

    protected string $icon = 'money-bill-wave';

    protected ?string $hint = 'Count the cash, write what is there. A shortage or an excess is booked by itself.';

    protected function rows(Request $request): LengthAwarePaginator
    {
        return CashCount::with(['account', 'currency', 'creator'])->orderByDesc('date')->orderByDesc('id')->paginate(per_page(25))->withQueryString();
    }

    protected function columns(): array
    {
        return [
            'No.' => fn (CashCount $c) => $c->number,
            'Date' => fn (CashCount $c) => fmt_date($c->date),
            'Account' => fn (CashCount $c) => $c->account->label().' · '.$c->currency->code,
            'In the books' => fn (CashCount $c) => money($c->book),
            'Counted' => fn (CashCount $c) => money($c->counted),
            'Difference' => fn (CashCount $c) => \App\Support\Num::isZero($c->difference) ? '—' : money($c->difference).' · '.(\App\Support\Num::isNegative($c->difference) ? __('Shortage') : __('Excess')),
            'Notes' => fn (CashCount $c) => $c->note,
            'User' => fn (CashCount $c) => $c->creator?->name,
        ];
    }

    private function boxes()
    {
        Accounts::ensureCash(Branch::main());

        return Account::active()->with('currency')->where('account_category_id', AccountCategory::byKey('cash')->id)->orderBy('name')->orderBy('currency_id')->get();
    }

    protected function fields(): array
    {
        $boxes = $this->boxes();

        return [
            ['name' => 'account_id', 'label' => 'Cash account', 'type' => 'select', 'default' => $boxes->first()?->id,
                'options' => $boxes->mapWithKeys(fn (Account $a) => [$a->id => $a->label().' · '.$a->currency->code.' · '.__('in the books').': '.money(Ledger::balance($a))])->all(),
                'rules' => ['required', Rule::in($boxes->pluck('id')->all())]],
            ['name' => 'counted', 'label' => 'Counted', 'type' => 'decimal', 'rules' => ['required', 'numeric', 'gte:0'], 'help' => 'What is in the box now, in the currency of the account.'],
            ['name' => 'note', 'label' => 'Notes', 'col' => 12, 'rules' => ['nullable', 'string', 'max:500']],
        ];
    }

    protected function record(array $data, Request $request): string
    {
        $count = Deals::countCash(Account::with('currency')->findOrFail($data['account_id']), $data['counted'], $data['note'] ?? null);

        return \App\Support\Num::isZero($count->difference)
            ? __('The cash agrees with the books.')
            : __(':kind of :amount booked.', ['kind' => \App\Support\Num::isNegative($count->difference) ? __('Shortage') : __('Excess'), 'amount' => money(\App\Support\Num::abs($count->difference), $count->currency->code)]);
    }

    protected function find(int $id): Model
    {
        return CashCount::with(['account', 'currency', 'creator', 'branch'])->findOrFail($id);
    }

    protected function cancel(Model $paper, ?string $reason): void
    {
        Deals::cancelCount($paper, $reason);
    }
}
