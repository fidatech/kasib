<?php

namespace App\Providers;

use App\Models\User;
use App\Tenancy\Tenancy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(Tenancy::class);
    }

    public function boot(): void
    {
        // The connection the application starts on holds the list of businesses (the central database).
        config(['tenancy.central_connection' => config('tenancy.central_connection') ?: config('database.default')]);

        // The Administrator role (the owner of a business) may do everything inside that business.
        Gate::before(fn ($user) => $user instanceof User && $user->isAdmin() ? true : null);
    }
}
