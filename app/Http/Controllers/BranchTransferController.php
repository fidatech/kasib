<?php

namespace App\Http\Controllers;

use App\Exceptions\UserError;
use App\Models\Account;
use App\Models\AccountCategory;
use App\Models\Branch;
use App\Models\BranchTransfer;
use App\Services\Accounts;
use App\Services\Branches;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/** Money sent to another branch, or received from one. Each branch sees the transfers it is a side of. */
class BranchTransferController extends CrudController
{
    protected string $model = BranchTransfer::class;

    protected string $route = 'branch-transfers';

    protected string $permission = 'transactions';

    protected string $title = 'Money Between Branches';

    protected string $singular = 'Transfer to a branch';

    protected string $icon = 'code-branch';

    protected array $search = ['number', 'note'];

    protected array $with = ['fromBranch', 'toBranch', 'fromAccount', 'toAccount', 'currency'];

    protected string $orderBy = 'id';

    protected string $orderDirection = 'desc';

    protected function query(Request $request): Builder
    {
        $own = (int) current_branch()->id;

        return BranchTransfer::query()->where(fn ($q) => $q->where('from_branch_id', $own)->orWhere('to_branch_id', $own));
    }

    protected function fields(?Model $record): array
    {
        $own = current_branch();
        $from = Accounts::money($own);
        // the cash, bank and saraf accounts of the other branches
        $others = Branch::withBooks()->where('is_active', true)->where('id', '!=', $own->id)->orderBy('id')->get()->keyBy('id');
        foreach ($others as $branch) {
            Accounts::ensureCash($branch);
        }
        $to = Account::with('currency')->whereIn('branch_id', $others->keys())->whereIn('account_category_id', AccountCategory::moneyIds())->where('is_active', true)->orderBy('branch_id')->orderBy('name')->get();

        return [
            ['name' => 'date', 'label' => 'Date', 'type' => 'date', 'default' => today_iso(), 'rules' => ['required', 'date']],
            ['name' => 'from_account_id', 'label' => 'From (this branch)', 'type' => 'select', 'options' => $from->mapWithKeys(fn (Account $a) => [$a->id => $a->label().' · '.$a->currency->code])->all(), 'rules' => ['required', Rule::in($from->pluck('id')->all())]],
            ['name' => 'to_account_id', 'label' => 'To (the other branch)', 'type' => 'select', 'options' => $to->mapWithKeys(fn (Account $a) => [$a->id => $others[$a->branch_id]->name.' · '.$a->label().' · '.$a->currency->code])->all(),
                'rules' => ['required', Rule::in($to->pluck('id')->all())], 'help' => 'An account of the same currency. The money is in the books of both branches at once.'],
            ['name' => 'amount', 'label' => 'Amount', 'type' => 'decimal', 'rules' => ['required', 'numeric', 'gt:0']],
            ['name' => 'note', 'label' => 'Details', 'col' => 12, 'rules' => ['nullable', 'string', 'max:500']],
        ];
    }

    protected function columns(): array
    {
        return [
            'No.' => fn (BranchTransfer $t) => $t->number,
            'Date' => fn (BranchTransfer $t) => fmt_date($t->date),
            'From' => fn (BranchTransfer $t) => $t->fromBranch->name.' · '.$t->fromAccount->label(),
            'To' => fn (BranchTransfer $t) => $t->toBranch->name.' · '.$t->toAccount->label(),
            'Amount' => fn (BranchTransfer $t) => money($t->amount, $t->currency->code),
            'Details' => fn (BranchTransfer $t) => $t->note,
            'Status' => fn (BranchTransfer $t) => $t->isCancelled() ? __('Cancelled') : __('In the books'),
        ];
    }

    /** A transfer is recorded by the service, in the books of both branches. */
    protected function save(Request $request, ?Model $record): Model
    {
        if ($record) {
            throw new UserError(__('A transfer is not changed. Cancel it and write it again.'));
        }
        $rules = [];
        foreach ($this->fields(null) as $field) {
            $rules[$field['name']] = $field['rules'];
        }
        $data = $request->validate($rules);

        return $this->saved[] = Branches::transfer(Account::with('currency')->findOrFail($data['from_account_id']), Account::with('currency')->findOrFail($data['to_account_id']), $data['date'], $data['amount'], $data['note'] ?? null);
    }

    protected function canEdit(Model $record): bool
    {
        return false;
    }

    protected function deleteProblem(Model $record): ?string
    {
        return __('A transfer is not deleted. Cancel it and it stays on the list.');
    }

    protected function rowLinks(Model $record): array
    {
        // the branch that sent it takes it back
        return ! $record->isCancelled() && (int) $record->from_branch_id === (int) current_branch()->id && auth()->user()->can('transactions.manage')
            ? [['label' => __('Cancel'), 'url' => route('branch-transfers.cancel', ['id' => $record->id]), 'post' => true, 'confirm' => __('Cancel this? It stays on the list, crossed out.')]] : [];
    }

    public function cancel(Request $request): RedirectResponse
    {
        Gate::authorize('transactions.manage');
        $transfer = BranchTransfer::where('from_branch_id', current_branch()->id)->findOrFail((int) $request->route('id'));
        Branches::cancel($transfer, $request->input('reason'));

        return back()->with('success', __(':number cancelled.', ['number' => $transfer->number]));
    }
}
