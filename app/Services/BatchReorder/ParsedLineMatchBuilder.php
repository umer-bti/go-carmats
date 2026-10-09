<?php

namespace App\Services\BatchReorder;

use Illuminate\Support\Collection;

/**
 * Builds match records directly from parsed OCR lines that already include listing IDs.
 */
class ParsedLineMatchBuilder
{
    /**
     * @param  array<int, array{
     *     raw_line: string,
     *     position: int|null,
     *     listing_reference_id: int|null,
     *     cleaned_name: string,
     *     normalized_name: string
     * }>  $parsedLines
     * @param  Collection<int, object>  $listings
     * @return array{
     *     matches: array<int, array<string, mixed>>,
     *     unmatched_lines: array<int, array<string, mixed>>
     * }
     */
    public function build(array $parsedLines, Collection $listings): array
    {
        $listingLookup = $listings->keyBy(fn ($listing) => (int) $listing->id);
        $matches = [];
        $unmatchedLines = [];
        $usedListingIds = [];

        foreach ($parsedLines as $lineIndex => $parsedLine) {
            $referenceId = $parsedLine['listing_reference_id'] ?? null;
            $listing = $referenceId !== null ? $listingLookup->get($referenceId) : null;

            BatchReorderLogger::step('Step 5 compare', 'Comparing parsed line against batch listings by listing ID', [
                'line_index' => $lineIndex,
                'raw_line' => $parsedLine['raw_line'],
                'position' => $parsedLine['position'],
                'listing_reference_id' => $referenceId,
                'listing_found_in_batch' => $listing !== null,
                'listing_already_used' => $listing !== null && in_array($listing->id, $usedListingIds, true),
                'batch_listing_ids' => $listingLookup->keys()->all(),
            ]);

            if ($listing === null || in_array($listing->id, $usedListingIds, true)) {
                $unmatchedReason = $referenceId === null
                    ? 'no listing_reference_id extracted from line'
                    : ($listing === null
                        ? "listing ID {$referenceId} does not exist in this batch"
                        : "listing ID {$referenceId} already matched by an earlier line (duplicate)");

                BatchReorderLogger::step('Step 5 unmatched', 'Parsed line left unmatched', [
                    'line_index' => $lineIndex,
                    'raw_line' => $parsedLine['raw_line'],
                    'position' => $parsedLine['position'],
                    'unmatched_reason' => $unmatchedReason,
                ]);

                $unmatchedLines[] = [
                    'line_index' => $lineIndex,
                    'raw_line' => $parsedLine['raw_line'],
                    'position' => $parsedLine['position'],
                    'cleaned_name' => $parsedLine['cleaned_name'],
                ];

                continue;
            }

            $usedListingIds[] = $listing->id;

            $matches[] = [
                'line_index' => $lineIndex,
                'raw_line' => $parsedLine['raw_line'],
                'position' => $parsedLine['position'],
                'cleaned_name' => $parsedLine['cleaned_name'],
                'order_id' => $listing->id,
                'listing_name' => (string) ($listing->make_model ?? ''),
                'match_type' => 'listing_id',
                'match_score' => 100.0,
            ];
        }

        return [
            'matches' => $matches,
            'unmatched_lines' => $unmatchedLines,
        ];
    }
}
