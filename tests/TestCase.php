<?php

namespace Tests;

use App\Models\Branch;
use App\Models\Central\Admin;
use App\Models\Central\Tenant;
use App\Models\Currency;
use App\Models\User;
use App\Services\Accounts;
use App\Services\Ledger;
use App\Services\Rates;
use App\Support\Permissions;
use App\Tenancy\Tenancy;
use App\Tenancy\TenantManager;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\URL;
use Spatie\Permission\Models\Role;

abstract class TestCase extends BaseTestCase
{
    protected ?Tenant $tenant = null;

    protected function setUp(): void
    {
        parent::setUp();

        // a fixed working day, so tests do not depend on the clock
        $this->travelTo(Carbon::parse('2026-11-13 10:00:00', 'Asia/Kabul'));

        // every test gets its own, empty folder of business databases
        config(['tenancy.sqlite_path' => storage_path('framework/testing/tenants')]);
        $this->removeTenantDatabases();
    }

    protected function tearDown(): void
    {
        if ($this->app) {
            app(Tenancy::class)->end();
            $this->removeTenantDatabases();
        }

        parent::tearDown();
    }

    /** Open a new business and step into it: requests now go to its address. */
    protected function business(array $attributes = []): Tenant
    {
        $tenant = app(TenantManager::class)->create($attributes + [
            'name' => 'Acme business',
            'slug' => 'acme',
            'owner_name' => 'Owner One',
            'owner_email' => 'owner@acme.test',
            'owner_password' => 'password123',
        ]);

        return $this->enter($tenant);
    }

    /** The older name of business(), kept for the tests of the administration panel. */
    protected function sarafi(array $attributes = [], bool $openDay = true): Tenant
    {
        return $this->business($attributes);
    }

    /** Write the rates of the business: ['USD' => '70', ...]. */
    protected function rates(array $rates): void
    {
        foreach ($rates as $code => $rate) {
            Rates::set(Branch::main(), Currency::where('code', $code)->firstOrFail(), $rate);
        }
    }

    protected function enter(Tenant $tenant): Tenant
    {
        app(Tenancy::class)->initialize($tenant);
        URL::forceRootUrl('http://'.$tenant->domain);
        auth()->shouldUse('web');

        return $this->tenant = $tenant;
    }

    /** Back to the address of the system administrator panel. */
    protected function central(): void
    {
        app(Tenancy::class)->end();
        URL::forceRootUrl('http://localhost');
    }

    /** The signed-in owner of the business we are in. */
    protected function owner(): User
    {
        $user = User::whereHas('roles', fn ($q) => $q->where('name', User::ADMIN_ROLE))->orderBy('id')->firstOrFail();
        $this->actingAs($user);

        return $user;
    }

    /** A signed-in user of the business we are in, whose role has only the given permissions. */
    protected function userWith(array $permissions): User
    {
        Permissions::sync();
        $role = Role::findOrCreate('Test role '.md5(implode(',', $permissions)), 'web');
        $role->syncPermissions($permissions);
        $user = User::factory()->create();
        $user->assignRole($role);
        $this->actingAs($user);

        return $user;
    }

    /** Put cash into the business before a test pays any out (cash can never go below zero). A foreign currency needs a rate first. */
    protected function openingCash(string $code, string $amount): void
    {
        $branch = Branch::main();
        $currency = Currency::where('code', $code)->firstOrFail();

        Ledger::post($branch, 'opening', today_iso(), 'Opening cash', [
            ['account' => Accounts::cash($branch, $currency), 'debit' => $amount],
            ['account' => Accounts::system($branch, 'opening'), 'credit' => Rates::toBase($amount, $currency, $branch)],
        ]);
    }

    /** A signed-in system administrator of the central panel. */
    protected function systemAdmin(): Admin
    {
        $admin = Admin::firstOrCreate(['email' => 'admin@fida.test'], ['name' => 'System Admin', 'password' => 'password123']);
        $this->actingAs($admin, 'admin');

        return $admin;
    }

    private function removeTenantDatabases(): void
    {
        foreach (glob(storage_path('framework/testing/tenants').'/*.sqlite') ?: [] as $file) {
            @unlink($file);
        }
    }
}
