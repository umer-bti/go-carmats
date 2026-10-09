<?php

namespace App\Services\BatchReorder;

use Illuminate\Support\Collection;

/**
 * Reorders batch listings using the order lines appear in extracted text.
 */
class ListingReorderService
{
    /**
     * Build the final listing order and processing summary.
     *
     * Matched listings are placed at the top in extraction order (top to bottom).
     * Any listings not matched from the upload remain at the end in their original order.
     *
     * @param  int[]  $currentOrderIds
     * @param  array<int, array{
     *     line_index: int,
     *     raw_line: string,
     *     position: int|null,
     *     cleaned_name: string,
     *     order_id: int,
     *     listing_name: string,
     *     match_type: string,
     *     match_score: float
     * }>  $matches
     * @param  array<int, array{
     *     line_index: int,
     *     raw_line: string,
     *     position: int|null,
     *     cleaned_name: string
     * }>  $unmatchedLines
     * @return array{
     *     ordered_ids: int[],
     *     summary: array{
     *         total_extracted_lines: int,
     *         total_matched_listings: int,
     *         total_unmatched_ocr_lines: int,
     *         listings_not_in_upload: array<int, array{order_id: int, listing_name: string}>,
     *         duplicate_positions: array<int, array{position: int, raw_lines: string[]}>,
     *         duplicate_matched_products: array<int, array{order_id: int, listing_name: string, raw_lines: string[]}>,
     *         matches_without_position: array<int, array{order_id: int, listing_name: string, raw_line: string}>
     *     }
     * }
     */
    public function reorder(array $currentOrderIds, array $matches, array $unmatchedLines, int $totalExtractedLines, Collection $listings): array
    {
        $listingLookup = $listings->keyBy('id');

        usort($matches, function (array $left, array $right): int {
            return ($left['line_index'] ?? 0) <=> ($right['line_index'] ?? 0);
        });

        $matchedOnTop = [];
        $duplicatePositions = $this->detectDuplicatePositions($matches);
        $duplicateMatchedProducts = [];
        $matchesWithoutPosition = [];

        foreach ($matches as $match) {
            if ($match['position'] === null) {
                $matchesWithoutPosition[] = [
                    'order_id' => $match['order_id'],
                    'listing_name' => $match['listing_name'],
                    'raw_line' => $match['raw_line'],
                ];
            }

            if (in_array($match['order_id'], $matchedOnTop, true)) {
                $duplicateMatchedProducts = $this->appendDuplicateProductEntry(
                    $duplicateMatchedProducts,
                    $match['order_id'],
                    $match['listing_name'],
                    $match['raw_line']
                );

                continue;
            }

            $matchedOnTop[] = $match['order_id'];
        }

        $remainingOrderIds = array_values(array_filter(
            $currentOrderIds,
            fn (int $orderId) => ! in_array($orderId, $matchedOnTop, true)
        ));

        $orderedIds = array_merge($matchedOnTop, $remainingOrderIds);

        BatchReorderLogger::step('Step 6a', 'Matched listings placed on top (by extraction order)', [
            'matched_on_top' => collect($matchedOnTop)->map(function (int $orderId) use ($listingLookup) {
                $listing = $listingLookup->get($orderId);

                return [
                    'order_id' => $orderId,
                    'make_model' => $listing->make_model ?? null,
                ];
            })->values()->all(),
        ]);

        BatchReorderLogger::step('Step 6b', 'Remaining listings kept at end', [
            'remaining_listings' => collect($remainingOrderIds)->map(function (int $orderId) use ($listingLookup) {
                $listing = $listingLookup->get($orderId);

                return [
                    'order_id' => $orderId,
                    'make_model' => $listing->make_model ?? null,
                ];
            })->values()->all(),
        ]);

        if ($duplicatePositions !== []) {
            BatchReorderLogger::step('Step 6c', 'Duplicate positions detected', [
                'duplicate_positions' => $duplicatePositions,
            ]);
        }

        if ($duplicateMatchedProducts !== []) {
            BatchReorderLogger::step('Step 6d', 'Duplicate matched products detected', [
                'duplicate_matched_products' => array_values($duplicateMatchedProducts),
            ]);
        }

        BatchReorderLogger::step('Step 6 complete', 'Final order built', [
            'ordered_ids' => $orderedIds,
        ]);

        $listingsNotInUpload = [];
        foreach ($remainingOrderIds as $orderId) {
            $listing = $listingLookup->get($orderId);

            $listingsNotInUpload[] = [
                'order_id' => $orderId,
                'listing_name' => (string) ($listing->make_model ?? ''),
            ];
        }

        $duplicatePositions = array_values(array_map(
            fn (array $entry) => [
                'position' => $entry['position'],
                'raw_lines' => array_values(array_unique($entry['raw_lines'])),
            ],
            $duplicatePositions
        ));

        $duplicateMatchedProducts = array_values(array_map(
            fn (array $entry) => [
                'order_id' => $entry['order_id'],
                'listing_name' => $entry['listing_name'],
                'raw_lines' => array_values(array_unique($entry['raw_lines'])),
            ],
            $duplicateMatchedProducts
        ));

        return [
            'ordered_ids' => $orderedIds,
            'summary' => [
                'total_extracted_lines' => $totalExtractedLines,
                'total_matched_listings' => count($matches),
                'total_unmatched_ocr_lines' => count($unmatchedLines),
                'listings_not_in_upload' => $listingsNotInUpload,
                'duplicate_positions' => $duplicatePositions,
                'duplicate_matched_products' => $duplicateMatchedProducts,
                'matches_without_position' => $matchesWithoutPosition,
            ],
        ];
    }

    /**
     * @param  array<int, array{
     *     line_index: int,
     *     raw_line: string,
     *     position: int|null,
     *     cleaned_name: string,
     *     order_id: int,
     *     listing_name: string,
     *     match_type: string,
     *     match_score: float
     * }>  $matches
     * @return array<int, array{position: int, raw_lines: string[]}>
     */
    protected function detectDuplicatePositions(array $matches): array
    {
        $positionLines = [];

        foreach ($matches as $match) {
            if ($match['position'] === null) {
                continue;
            }

            $positionLines[$match['position']][] = $match['raw_line'];
        }

        $duplicatePositions = [];

        foreach ($positionLines as $position => $rawLines) {
            if (count($rawLines) < 2) {
                continue;
            }

            $duplicatePositions[$position] = [
                'position' => $position,
                'raw_lines' => $rawLines,
            ];
        }

        return $duplicatePositions;
    }

    /**
     * @param  array<int, array{order_id: int, listing_name: string, raw_lines: string[]}>  $duplicateMatchedProducts
     * @return array<int, array{order_id: int, listing_name: string, raw_lines: string[]}>
     */
    protected function appendDuplicateProductEntry(array $duplicateMatchedProducts, int $orderId, string $listingName, string $rawLine): array
    {
        if (! isset($duplicateMatchedProducts[$orderId])) {
            $duplicateMatchedProducts[$orderId] = [
                'order_id' => $orderId,
                'listing_name' => $listingName,
                'raw_lines' => [],
            ];
        }

        $duplicateMatchedProducts[$orderId]['raw_lines'][] = $rawLine;

        return $duplicateMatchedProducts;
    }
}
