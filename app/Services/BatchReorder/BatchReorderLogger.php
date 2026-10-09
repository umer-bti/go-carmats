<?php

namespace App\Services\BatchReorder;

use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Writes step-by-step logs for the upload-and-reorder pipeline.
 *
 * Logs are written to the dedicated "batch_reorder" channel
 * (storage/logs/batch-reorder.log) so the full pipeline can be audited
 * without flooding the main application log.
 */
class BatchReorderLogger
{
    public static function step(string $step, string $message, array $context = []): void
    {
        try {
            Log::channel('batch_reorder')->info("[Batch Reorder] {$step} — {$message}", $context);
        } catch (Throwable) {
            Log::info("[Batch Reorder] {$step} — {$message}", $context);
        }
    }
}
