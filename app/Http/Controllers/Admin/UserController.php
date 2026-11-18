<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\CrudController;
use App\Models\Branch;
use App\Models\User;
use App\Support\Permissions;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Spatie\Permission\Models\Role;

class UserController extends CrudController
{
    protected string $model = User::class;

    protected string $route = 'users';

    protected string $permission = 'users';

    protected string $title = 'Users';

    protected string $singular = 'User';

    protected string $icon = 'users';

    protected array $search = ['name', 'email'];

    protected array $with = ['roles', 'branch'];

    protected function fields(?Model $record): array
    {
        Permissions::sync();

        return [
            ['name' => 'name', 'label' => 'Full name', 'rules' => ['required', 'string', 'max:255']],
            ['name' => 'email', 'label' => 'Email address', 'type' => 'email', 'rules' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($record?->id)]],
            ['name' => 'password', 'label' => 'Password', 'type' => 'password', 'rules' => [$record ? 'nullable' : 'required', Password::min(8)],
                'help' => $record ? 'Leave empty to keep the current password' : 'At least 8 characters'],
            ['name' => 'role', 'label' => 'Role', 'type' => 'select', 'virtual' => true, 'value' => $record?->roles->first()?->name,
                'options' => Role::orderBy('name')->pluck('name', 'name')->all(), 'rules' => ['required', 'exists:roles,name']],
            ['name' => 'branch_id', 'label' => 'Branch', 'type' => 'select',
                'options' => Branch::withBooks()->where('is_active', true)->orderBy('id')->pluck('name', 'id')->all(), 'rules' => ['required', Rule::exists('branches', 'id')->whereIn('type', [Branch::MAIN, Branch::DEPENDENT])]],
            ['name' => 'transaction_limit', 'label' => 'Transaction limit (AFN)', 'type' => 'decimal', 'default' => '0', 'rules' => ['required', 'numeric', 'min:0'],
                'help' => 'For one transaction. Above it the transaction waits for the compliance officer.'],
            ['name' => 'calendar', 'label' => 'Calendar', 'type' => 'select', 'default' => 'shamsi',
                'options' => ['gregorian' => __('Gregorian (27/10/2026)'), 'shamsi' => __('Solar Hijri (1405/08/05)')], 'rules' => ['required', 'in:gregorian,shamsi']],
            ['name' => 'is_active', 'label' => 'May sign in', 'type' => 'checkbox', 'default' => true],
        ];
    }

    protected function columns(): array
    {
        return [
            'Full name' => fn (User $u) => $u->name,
            'Email address' => fn (User $u) => $u->email,
            'Branch' => fn (User $u) => $u->branch?->name,
            'Role' => fn (User $u) => $u->roles->pluck('name')->implode(', '),
            'Transaction limit (AFN)' => fn (User $u) => money($u->transaction_limit),
            'May sign in' => fn (User $u) => $u->is_active ? __('Yes') : __('No'),
        ];
    }

    protected function beforeSave(array $data, ?Model $record, Request $request): array
    {
        // the plan of the business says how many users it may have
        $limit = (int) (tenant()?->max_users ?? 0);
        if (! $record && $limit > 0 && User::where('email', '!=', \App\Tenancy\Support::EMAIL)->count() >= $limit) {
            throw new \App\Exceptions\UserError(__('Your plan allows :count users. Ask Fida Technologies for a larger plan to add more.', ['count' => $limit]));
        }
        // nobody can lock themselves out
        if ($record && $record->is($request->user())) {
            $data['is_active'] = true;
        }

        return $data;
    }

    protected function afterSave(Model $record, array $virtual, Request $request, bool $created): void
    {
        $keepAdmin = $record->is($request->user()) && $record->isAdmin();
        $record->syncRoles([$keepAdmin ? User::ADMIN_ROLE : $virtual['role']]);
    }

    protected function deleteProblem(Model $record): ?string
    {
        return $record->is(request()->user()) ? __('You cannot delete your own user.') : null;
    }
}
