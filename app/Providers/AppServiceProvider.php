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

        // whereDay('date', '<=', $day): a filter by day that compares the column itself, so its index is used.
        // (whereDate wraps the column in DATE(), which makes the database read every row of a large table.)
        \Illuminate\Database\Query\Builder::macro('whereDay', function (string $column, mixed $operator, mixed $value = null) {
            if (func_num_args() === 2) {
                [$value, $operator] = [$operator, '='];
            }
            $day = $value instanceof \DateTimeInterface ? $value->format('Y-m-d') : substr((string) $value, 0, 10);
            $end = $day.' 23:59:59';

            /** @var \Illuminate\Database\Query\Builder $this */
            return match ($operator) {
                '=' => $this->whereBetween($column, [$day, $end]),
                '>=', '<' => $this->where($column, $operator, $day),
                '<=', '>' => $this->where($column, $operator, $end),
                default => $this->whereDate($column, $operator, $day),
            };
        });

        // The Administrator role (the owner of a business) may do everything inside that business.
        Gate::before(fn ($user) => $user instanceof User && $user->isAdmin() ? true : null);
    }
}
