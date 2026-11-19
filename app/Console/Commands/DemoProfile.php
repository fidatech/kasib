<?php

namespace App\Console\Commands;

use App\Models\Central\Tenant;
use App\Models\Party;
use App\Models\User;
use App\Services\Demo;
use App\Tenancy\Tenancy;
use Illuminate\Console\Command;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Opens the main pages of the demo as its user and says how long each took and which queries were slow. */
class DemoProfile extends Command
{
    protected $signature = 'demo:profile {--slow=40 : list queries slower than this many milliseconds}';

    protected $description = 'Time the main pages of the demo business and list their slow queries';

    public function handle(): int
    {
        $tenant = Tenant::where('slug', Demo::slug())->firstOrFail();
        [$user, $customer] = app(Tenancy::class)->run($tenant, fn () => [User::orderBy('id')->firstOrFail(), Party::whereHas('group', fn ($q) => $q->where('key', 'customers'))->orderByDesc('id')->firstOrFail()]);
        $pages = ['/', '/accounts/customers', '/accounts/customers/'.$customer->id.'/statement', '/accounts/bank', '/money-in', '/money-out', '/transfers', '/journal', '/treasury',
            '/trade/sales', '/items', '/reports/stock', '/reports/trial-balance', '/reports/profit-and-loss', '/reports/balance-sheet', '/reports/balances', '/reports/due', '/expenses', '/reports'];
        $slow = (float) $this->option('slow');
        $worst = [];
        foreach ($pages as $page) {
            $queries = [];
            DB::flushQueryLog();
            $listener = function ($query) use (&$queries) {
                $queries[] = [$query->time, $query->sql];
            };
            app('events')->listen(\Illuminate\Database\Events\QueryExecuted::class, $listener);
            $started = microtime(true);
            $request = Request::create('http://'.$tenant->domain.$page, 'GET');
            $request->setLaravelSession(app('session')->driver('array'));
            app('auth')->guard('web')->setUser($user);
            $response = app(Kernel::class)->handle($request);
            $ms = round((microtime(true) - $started) * 1000);
            app('events')->forget(\Illuminate\Database\Events\QueryExecuted::class);
            $total = round(array_sum(array_column($queries, 0)));
            $this->line(sprintf('%-48s %3d  %6d ms  %4d queries  %6d ms in queries  %5d KB', $page, $response->getStatusCode(), $ms, count($queries), $total, strlen((string) $response->getContent()) / 1024));
            foreach ($queries as [$time, $sql]) {
                if ($time >= $slow) {
                    $worst[] = sprintf('%7.0f ms  %-26s %s', $time, $page, mb_substr(preg_replace('/\s+/', ' ', $sql), 0, 300));
                }
            }
        }
        rsort($worst, SORT_NATURAL);
        $this->line('');
        $this->line('Slow queries:');
        foreach (array_slice($worst, 0, 40) as $line) {
            $this->line($line);
        }

        return self::SUCCESS;
    }
}
