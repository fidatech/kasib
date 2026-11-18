<?php

namespace App\Console\Commands;

use App\Models\Branch;
use App\Models\Central\Tenant;
use App\Services\AssetService;
use App\Tenancy\Tenancy;
use Illuminate\Console\Command;
use Throwable;

class BooksDaily extends Command
{
    protected $signature = 'books:daily';

    protected $description = 'The work the books do by themselves every night: the depreciation of the months that ended';

    public function handle(Tenancy $tenancy): int
    {
        $failed = 0;
        foreach (Tenant::where('status', Tenant::ACTIVE)->orderBy('id')->get() as $tenant) {
            try {
                $months = $tenancy->run($tenant, fn () => Branch::withBooks()->get()->sum(fn (Branch $branch) => AssetService::catchUp($branch)));
                $this->info($tenant->slug.': '.$months);
            } catch (Throwable $e) {
                $failed++;
                report($e);
                $this->error($tenant->slug.': '.$e->getMessage());
            }
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
