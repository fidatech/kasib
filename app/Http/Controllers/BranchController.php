<?php

namespace App\Http\Controllers;

use App\Exceptions\UserError;
use App\Models\Branch;
use App\Models\User;
use App\Services\Branches;
use App\Support\Permissions;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Spatie\Permission\Models\Role;

/**
 * Branches (version 2, the Pro plan). Every branch keeps books of its own: its cash, its
 * customers' balances, its stock, its profit. People of the main branch who manage the
 * settings add branches here and switch between them from the top bar.
 */
class BranchController extends CrudController
{
    protected string $model = Branch::class;

    protected string $route = 'branches';

    protected string $permission = 'settings';

    protected string $title = 'Branches';

    protected string $singular = 'Branch';

    protected string $icon = 'code-branch';

    protected array $search = ['name', 'name_local', 'code', 'city'];

    protected string $orderBy = 'id';

    protected function query(Request $request): Builder
    {
        return Branch::withBooks();
    }

    protected function fields(?Model $record): array
    {
        $fields = [
            ['name' => 'name', 'label' => 'Branch name', 'rules' => ['required', 'string', 'max:150']],
            ['name' => 'name_local', 'label' => 'Name in Dari or Pashto', 'rules' => ['nullable', 'string', 'max:150']],
            ['name' => 'code', 'label' => 'Short code', 'rules' => ['required', 'string', 'max:20', 'alpha_dash', Rule::unique('branches')->ignore($record?->id)], 'help' => 'A few letters that mark this branch, for example HRT'],
            ['name' => 'city', 'label' => 'City', 'rules' => ['nullable', 'string', 'max:100']],
            ['name' => 'phone', 'label' => 'Phone', 'rules' => ['nullable', 'string', 'max:50']],
            ['name' => 'address', 'label' => 'Address', 'col' => 12, 'rules' => ['nullable', 'string', 'max:255']],
        ];
        if (! $record?->isMain()) {
            $fields[] = ['name' => 'is_active', 'label' => 'In use', 'type' => 'checkbox', 'default' => true];
        }
        // a new branch gets its first user on the same page
        if (! $record) {
            Permissions::sync();
            $roles = Role::where('name', '!=', User::ADMIN_ROLE)->orderBy('name')->pluck('name', 'name')->all();
            $fields[] = ['name' => 'user_name', 'label' => 'Name of its first user', 'virtual' => true, 'rules' => ['nullable', 'required_with:user_email', 'string', 'max:255'], 'help' => 'Leave the four boxes empty to add its users later, under Users.'];
            $fields[] = ['name' => 'user_email', 'label' => 'Email address', 'type' => 'email', 'virtual' => true, 'rules' => ['nullable', 'email', 'max:255', Rule::unique('users', 'email')]];
            $fields[] = ['name' => 'user_password', 'label' => 'Password', 'type' => 'password', 'virtual' => true, 'rules' => ['nullable', 'required_with:user_email', Password::min(8)]];
            $fields[] = ['name' => 'user_role', 'label' => 'Role', 'type' => 'select', 'virtual' => true, 'options' => $roles, 'rules' => ['nullable', 'required_with:user_email', Rule::in(array_keys($roles))]];
        }

        return $fields;
    }

    protected function columns(): array
    {
        return [
            'Branch name' => fn (Branch $b) => $b->name,
            'Short code' => fn (Branch $b) => $b->code,
            'Kind' => fn (Branch $b) => $b->isMain() ? __('Main branch') : __('Branch'),
            'City' => fn (Branch $b) => $b->city,
            'Users' => fn (Branch $b) => $b->users()->count(),
            'In use' => fn (Branch $b) => $b->is_active ? __('Yes') : __('No'),
        ];
    }

    public function callAction($method, $parameters)
    {
        // branches are made and changed from the main branch
        if (in_array($method, ['create', 'store', 'edit', 'update', 'destroy'], true) && ! can_oversee()) {
            abort(403);
        }

        return parent::callAction($method, $parameters);
    }

    /** Codes are kept in capital letters, so "hrt" and "HRT" are the same code. */
    protected function save(Request $request, ?Model $record): Model
    {
        $request->merge(['code' => strtoupper(trim((string) $request->input('code')))]);

        return parent::save($request, $record);
    }

    protected function beforeSave(array $data, ?Model $record, Request $request): array
    {
        if ($record?->isMain()) {
            return $data;
        }
        // the subscription says how many branches a business may have
        $limit = tenant()?->max_branches;
        $wantsSeat = ! empty($data['is_active']);
        $hadSeat = $record && $record->is_active;
        if ($limit !== null && $wantsSeat && ! $hadSeat && Branch::where('type', Branch::DEPENDENT)->where('is_active', true)->count() >= $limit) {
            throw new UserError(__('Your subscription allows :count branches besides the main one. Ask for more under Support.', ['count' => $limit]));
        }

        return $data + ($record ? [] : ['type' => Branch::DEPENDENT, 'country' => 'Afghanistan']);
    }

    protected function afterSave(Model $record, array $virtual, Request $request, bool $created): void
    {
        if (! $created) {
            return;
        }
        Branches::prepare($record);
        if (! empty($virtual['user_email'])) {
            $user = User::create(['name' => $virtual['user_name'], 'email' => strtolower($virtual['user_email']), 'password' => $virtual['user_password'], 'branch_id' => $record->id, 'is_active' => true, 'transaction_limit' => 0]);
            $user->syncRoles([$virtual['user_role']]);
            audit('created', $user, $user->email.' ('.$record->name.')');
        }
    }

    protected function deleteProblem(Model $record): ?string
    {
        if ($record->isMain()) {
            return __('The main branch cannot be deleted.');
        }
        if ($record->users()->exists() || \App\Models\JournalEntry::where('branch_id', $record->id)->exists()) {
            return __('This branch has users or entries in its books. Switch it off instead of deleting it.');
        }

        return null;
    }

    public function destroy(Request $request): RedirectResponse
    {
        // an empty branch still has the accounts and rates it was made ready with
        $branch = Branch::withBooks()->find((int) $request->route('id'));
        if ($branch && ! $this->deleteProblem($branch)) {
            \App\Models\Account::where('branch_id', $branch->id)->delete();
            \App\Models\ExchangeRate::where('branch_id', $branch->id)->delete();
        }

        return parent::destroy($request);
    }

    /** Work in another branch: everything seen and written from now on belongs to it. */
    public function switch(Request $request): RedirectResponse
    {
        abort_unless(can_oversee(), 403);
        $branch = Branch::withBooks()->where('is_active', true)->findOrFail((int) $request->input('branch'));
        $request->session()->put('working_branch', $branch->id);
        Branch::forgetWorking();
        audit('switched branch', null, $branch->name);

        return redirect()->route('dashboard')->with('success', __('You are now working in :branch.', ['branch' => $branch->name]));
    }
}
