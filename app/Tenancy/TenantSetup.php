<?php

namespace App\Tenancy;

use App\Models\Branch;
use App\Models\Central\Tenant;
use App\Models\Setting;
use App\Models\User;
use App\Services\Accounts;
use App\Services\Chart;
use App\Support\Permissions;
use App\Support\Roles;

/** The first records of a new business: roles, the main branch, the owner and the settings. */
class TenantSetup
{
    /** Records every business needs and that a new version may add to: permissions, chart of accounts, currencies. */
    public function baseData(): void
    {
        Permissions::sync();
        Chart::seed();
    }

    public function run(Tenant $tenant, array $data): void
    {
        $this->baseData();
        Roles::createDefaults();

        $main = Branch::create([
            'name' => $data['main_branch_name'] ?? 'Main branch',
            'code' => 'MAIN',
            'type' => Branch::MAIN,
        ]);

        $owner = User::create([
            'name' => $data['owner_name'],
            'email' => strtolower($data['owner_email']),
            'password' => $data['owner_password'],
            'branch_id' => $main->id,
            'calendar' => 'shamsi',
        ]);
        $owner->assignRole(User::ADMIN_ROLE);

        Accounts::ensureCash($main);

        foreach (Setting::DEFAULTS + ['company_name' => $tenant->name, 'company_name_local' => $tenant->name_local] as $key => $value) {
            Setting::updateOrCreate(['key' => $key], ['value' => $value]);
        }
    }
}
