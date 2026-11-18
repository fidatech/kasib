<?php

namespace App\Support;

use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class Permissions
{
    /** @return array<int, string> every permission name: sales.view, sales.manage ... */
    public static function all(): array
    {
        $names = [];
        foreach (array_keys(self::modules()) as $module) {
            $names[] = $module.'.view';
            $names[] = $module.'.manage';
        }

        return $names;
    }

    /**
     * The modules a role can be given: those of config/permissions.php, and one for every
     * account category, for a user who may work with that category alone ("accounts" covers all).
     *
     * @return array<string, string> module => what it is
     */
    public static function modules(): array
    {
        $modules = config('permissions');
        if (tenant()) {
            foreach (\App\Models\AccountGroup::orderBy('sort')->get() as $group) {
                $modules['group-'.$group->key] = __('Only the accounts of: :name', ['name' => $group->label()]);
            }
        }

        return $modules;
    }

    /** May the user see ("view") or change ("manage") the accounts of this category? */
    public static function group(?User $user, string $key, string $what): bool
    {
        return (bool) ($user && ($user->can('accounts.'.$what) || $user->can('group-'.$key.'.'.$what)));
    }

    /** Make sure the database knows every permission and the Administrator role exists. */
    public static function sync(): void
    {
        foreach (self::all() as $name) {
            Permission::findOrCreate($name, 'web');
        }
        Role::findOrCreate(User::ADMIN_ROLE, 'web');
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
