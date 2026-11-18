<?php

namespace App\Console\Commands;

use App\Models\Central\Tenant;
use App\Services\Summary;
use App\Tenancy\Tenancy;
use Illuminate\Console\Command;
use Throwable;

class BooksEvening extends Command
{
    protected $signature = 'books:evening';

    protected $description = 'Every hour: the evening summary of each business whose hour has come';

    public function handle(Tenancy $tenancy): int
    {
        foreach (Tenant::where('status', Tenant::ACTIVE)->orderBy('id')->get() as $tenant) {
            try {
                if ($tenancy->run($tenant, fn () => Summary::tick())) {
                    $this->info($tenant->slug.': summary sent');
                }
            } catch (Throwable $e) {
                report($e);
                $this->error($tenant->slug.': '.$e->getMessage());
            }
        }

        return self::SUCCESS;
    }
}
