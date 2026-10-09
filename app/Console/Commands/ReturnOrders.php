<?php

namespace App\Console\Commands;

use App\Services\AmazonService;
use Illuminate\Console\Command;

class ReturnOrders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:return-orders';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle(AmazonService $amazonService)
    {
        $amazonService->getReturns();
    }
}
