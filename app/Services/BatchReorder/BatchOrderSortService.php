<?php

namespace App\Services\BatchReorder;

use App\Models\Batch;
use App\Models\BatchOrder;
use Illuminate\Support\Facades\DB;

/**
 * Persists and applies custom sort order for orders within a batch.
 */
class BatchOrderSortService
{
    /**
     * Save the display order for a batch's orders.
     *
     * @param  int[]  $orderedOrderIds
     */
    public function saveOrder(Batch $batch, array $orderedOrderIds): void
    {
        BatchReorderLogger::step('Step 7a', 'Saving order to database', [
            'batch_id' => $batch->id,
            'ordered_ids' => $orderedOrderIds,
        ]);

        $batchOrderIds = BatchOrder::query()
            ->where('batch_id', $batch->id)
            ->pluck('order_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $validOrderedIds = array_values(array_filter(
            $orderedOrderIds,
            fn (int $orderId) => in_array($orderId, $batchOrderIds, true)
        ));

        $remainingIds = array_values(array_filter(
            $batchOrderIds,
            fn (int $orderId) => ! in_array($orderId, $validOrderedIds, true)
        ));

        $finalOrder = array_merge($validOrderedIds, $remainingIds);

        // Atomic: a failure mid-way must not leave the batch half-reordered.
        DB::transaction(function () use ($batch, $finalOrder) {
            foreach ($finalOrder as $index => $orderId) {
                $sortOrder = $index + 1;

                BatchOrder::query()
                    ->where('batch_id', $batch->id)
                    ->where('order_id', $orderId)
                    ->update(['sort_order' => $sortOrder]);

                BatchReorderLogger::step('Step 7b.'.($index + 1), 'Saved sort order for listing', [
                    'batch_id' => $batch->id,
                    'order_id' => $orderId,
                    'sort_order' => $sortOrder,
                ]);
            }
        });

        BatchReorderLogger::step('Step 7 complete', 'Database order saved');
    }
}
