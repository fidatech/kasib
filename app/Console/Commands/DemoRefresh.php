<?php

namespace App\Console\Commands;

use App\Services\Demo;
use Illuminate\Console\Command;

class DemoRefresh extends Command
{
    protected $signature = 'demo:refresh {plan? : pro or starter; both when left out}';

    protected $description = 'Make the demo businesses of the website (Pro and Starter), or empty them and fill them with their example papers again';

    public function handle(): int
    {
        if (! Demo::slugs()) {
            $this->warn('No demo: DEMO_SLUG and DEMO_STARTER_SLUG are empty.');

            return self::SUCCESS;
        }
        @set_time_limit(0);
        @ini_set('memory_limit', '512M');
        $plan = $this->argument('plan');
        if ($plan !== null && ! isset(Demo::slugs()[$plan])) {
            $this->error('No demo for the plan '.$plan.'.');

            return self::FAILURE;
        }
        foreach (array_keys(Demo::slugs()) as $which) {
            if ($plan !== null && $plan !== $which) {
                continue;
            }
            $started = microtime(true);
            $tenant = Demo::refresh($which)[$which];
            $this->info('The '.ucfirst($which).' demo is ready at '.$tenant->domain.' ('.config('tenancy.demo.email').'), '.config('tenancy.demo.transactions').' papers of earlier years, in '.round(microtime(true) - $started).' seconds.');
        }

        return self::SUCCESS;
    }
}
