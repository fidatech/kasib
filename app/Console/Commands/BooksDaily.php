<?php

namespace App\Console\Commands;

use App\Models\Branch;
use App\Models\Central\Tenant;
use App\Services\AssetService;
use App\Services\OpeningBalances;
use App\Tenancy\Tenancy;
use Illuminate\Console\Command;
use Throwable;

class BooksDaily extends Command
{
    protected $signature = 'books:daily';

    protected $description = 'The work the books do by themselves every night: the depreciation of the months that ended and the recurring expenses that are due';

    public function handle(Tenancy $tenancy): int
    {
        $failed = 0;
        foreach (Tenant::where('status', Tenant::ACTIVE)->orderBy('id')->get() as $tenant) {
            try {
                $done = $tenancy->run($tenant, fn () => [Branch::withBooks()->get()->sum(fn (Branch $branch) => AssetService::catchUp($branch)), Branch::withBooks()->get()->sum(fn (Branch $branch) => Branch::as($branch, fn () => OpeningBalances::runRecurring())), Branch::withBooks()->get()->sum(fn (Branch $branch) => Branch::as($branch, fn () => \App\Services\Whatsapp::remindDue())), \App\Services\Summary::monthly(), \App\Services\Schedules::expiring()]);
                $this->info($tenant->slug.': '.$done[0].' months of depreciation, '.$done[1].' recurring expenses, '.$done[2].' reminders, '.$done[3].' balances sent');
            } catch (Throwable $e) {
                $failed++;
                report($e);
                $this->error($tenant->slug.': '.$e->getMessage());
            }
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
