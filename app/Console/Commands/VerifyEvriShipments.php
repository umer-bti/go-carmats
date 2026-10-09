<?php

namespace App\Console\Commands;

use App\Services\EvriVerificationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class VerifyEvriShipments extends Command
{
    protected $signature = 'evri:verify-shipments {--limit= : Max orders to verify in this run}';

    protected $description = 'Confirm Evri-shipped orders against Evri and update evri_shipment_verifications';

    public function handle(EvriVerificationService $verificationService): int
    {
        $limit = $this->option('limit') !== null
            ? (int) $this->option('limit')
            : null;

        $this->info('Starting Evri shipment verification...');

        $counts = $verificationService->verifyDueOrders($limit);

        Log::info('Evri shipment verification completed', $counts);

        $this->table(
            ['Metric', 'Count'],
            [
                ['Processed', $counts['processed']],
                ['Verified', $counts['verified']],
                ['Mismatch', $counts['mismatch']],
                ['Errors', $counts['errors']],
            ]
        );

        if ($counts['processed'] === 0) {
            $this->comment('No Evri orders were due for verification.');
        }

        return self::SUCCESS;
    }
}
