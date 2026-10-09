<?php

namespace App\Services\BatchReorder;

use Illuminate\Support\Collection;

/**
 * Detects batch listings that were not returned by structured label extraction.
 */
class StructuredLabelGapDetector
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
     * @return Collection<int, object>
     */
    public function findMissing(array $parsedLines, Collection $listings): Collection
    {
        $foundListingIds = collect($parsedLines)
            ->pluck('listing_reference_id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        return $listings
            ->filter(fn ($listing) => ! in_array((int) $listing->id, $foundListingIds, true))
            ->values();
    }

    /**
     * @param  Collection<int, object>  $missingListings
     * @return array<int, array{id: int, make_model: string|null}>
     */
    public function toLogContext(Collection $missingListings): array
    {
        return $missingListings
            ->map(fn ($listing) => [
                'id' => (int) $listing->id,
                'make_model' => $listing->make_model ?? null,
            ])
            ->values()
            ->all();
    }
}
