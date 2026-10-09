<?php

namespace App\Console\Commands;

use App\Services\EvriVerificationService;
use Illuminate\Console\Command;

class EvriTestTracking extends Command
{
    protected $signature = 'evri:test-tracking {barcode : Evri barcode to look up}';

    protected $description = 'Test Evri tracking API and print exact HTTP/exception errors';

    public function handle(EvriVerificationService $verificationService): int
    {
        $barcode = (string) $this->argument('barcode');

        $this->info("Testing Evri tracking for: {$barcode}");
        $this->newLine();

        $lookup = $verificationService->lookupEvriTrackingWithDebug($barcode);

        if (! empty($lookup['error_summary'])) {
            $this->error('Error summary:');
            $this->line($lookup['error_summary']);
            $this->newLine();
        }

        if ($lookup['barcode_used']) {
            $this->info('Barcode used: ' . $lookup['barcode_used']);
            $this->newLine();
        }

        foreach ($lookup['debug']['attempts'] ?? [] as $index => $attempt) {
            $this->line('--- Attempt ' . ($index + 1) . ' ---');
            $this->line('Endpoint: ' . ($attempt['endpoint'] ?? 'n/a'));
            $this->line('Barcode:  ' . ($attempt['barcode'] ?? 'n/a'));

            if (! empty($attempt['exception'])) {
                $this->line('Result:   EXCEPTION');
                $this->line('Error:    ' . $attempt['exception']);
                if (! empty($attempt['exception_class'])) {
                    $this->line('Class:    ' . $attempt['exception_class']);
                }
            } else {
                $this->line('Result:   HTTP ' . ($attempt['http_status'] ?? 'no response'));
                if (! empty($attempt['failure_reason'])) {
                    $this->line('Reason:   ' . $attempt['failure_reason']);
                }
                if (! empty($attempt['body_preview'])) {
                    $this->line('Body:     ' . substr((string) $attempt['body_preview'], 0, 500));
                }
                if (is_array($attempt['parsed'] ?? null)) {
                    $this->line('Parsed:   ' . json_encode($attempt['parsed'], JSON_UNESCAPED_SLASHES));
                }
            }

            $this->newLine();
        }

        if (($lookup['debug']['attempts'] ?? []) === []) {
            $this->warn('No API attempts were made. Check Evri settings and config/evri.php tracking_endpoints.');
        }

        return self::SUCCESS;
    }
}
