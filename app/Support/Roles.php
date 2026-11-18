<?php

namespace App\Support;

use App\Models\User;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/** The usual jobs in a business. A business may change them or add its own. */
class Roles
{
    /** role => modules it may change; the modules after "view" it may only look at */
    public const DEFAULTS = [
        'Manager' => ['manage' => ['accounts', 'transactions', 'expenses', 'days', 'capital', 'assets', 'liabilities'], 'view' => ['journal', 'reports', 'currencies', 'users', 'audit']],
        'Cashier' => ['manage' => ['transactions', 'expenses'], 'view' => ['accounts', 'journal', 'days']],
        'Accountant' => ['manage' => ['accounts', 'transactions', 'expenses', 'days', 'journal', 'assets', 'liabilities'], 'view' => ['capital', 'reports', 'currencies']],
        // an outside accountant or auditor: reads everything, changes nothing
        'Auditor' => ['manage' => [], 'view' => ['accounts', 'transactions', 'expenses', 'days', 'journal', 'capital', 'assets', 'liabilities', 'reports', 'currencies', 'audit']],
    ];

    /** A role that already exists keeps the permissions it has now. */
    public static function createDefaults(): void
    {
        Permissions::sync();
        $known = Permissions::all();

        Role::findOrCreate(User::ADMIN_ROLE, 'web');

        foreach (self::DEFAULTS as $name => $modules) {
            if (Role::where('name', $name)->where('guard_name', 'web')->exists()) {
                continue;
            }
            $permissions = [];
            foreach ($modules['manage'] as $module) {
                $permissions[] = $module.'.view';
                $permissions[] = $module.'.manage';
            }
            foreach ($modules['view'] as $module) {
                $permissions[] = $module.'.view';
            }
            Role::create(['name' => $name, 'guard_name' => 'web'])->syncPermissions(array_values(array_intersect($permissions, $known)));
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
