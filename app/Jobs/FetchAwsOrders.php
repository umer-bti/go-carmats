<?php

namespace App\Jobs;

use App\Services\shipStationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class FetchAwsOrders implements ShouldQueue
{
    use Queueable;


    /**
     * Create a new job instance.
     */
    public function __construct()
    {

    }

    /**
     * Execute the job.
     */
    public function handle(shipStationService $shipStationService): void
    {
        $shipStationService->getOrders();
    }
}
