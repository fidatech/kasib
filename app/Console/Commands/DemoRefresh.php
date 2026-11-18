<?php

namespace App\Console\Commands;

use App\Services\Demo;
use Illuminate\Console\Command;

class DemoRefresh extends Command
{
    protected $signature = 'demo:refresh';

    protected $description = 'Make the demo business of the website, or empty it and fill it with its example papers again';

    public function handle(): int
    {
        if (Demo::slug() === '') {
            $this->warn('No demo: DEMO_SLUG is empty.');

            return self::SUCCESS;
        }
        $tenant = Demo::refresh();
        $this->info('The demo is ready at '.$tenant->domain.' ('.config('tenancy.demo.email').').');

        return self::SUCCESS;
    }
}
