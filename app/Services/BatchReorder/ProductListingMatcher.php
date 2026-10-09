<?php

namespace App\Services\BatchReorder;

use Illuminate\Support\Collection;

/**
 * Matches parsed OCR/text lines to existing batch order listings.
 */
class ProductListingMatcher
{
    public function __construct(
        protected ProductNameNormalizer $normalizer
    ) {}

    /**
     * Match parsed lines against the current listings.
     *
     * @param  array<int, array{
     *     raw_line: string,
     *     position: int|null,
     *     listing_reference_id: int|null,
     *     cleaned_name: string,
     *     normalized_name: string
     * }>  $parsedLines
     * @param  Collection<int, object>  $listings  Items must expose id, order_id, and make_model.
     * @return array{
     *     matches: array<int, array{
     *         line_index: int,
     *         raw_line: string,
     *         position: int|null,
     *         cleaned_name: string,
     *         order_id: int,
     *         listing_name: string,
     *         match_type: string,
     *         match_score: float
     *     }>,
     *     unmatched_lines: array<int, array{
     *         line_index: int,
     *         raw_line: string,
     *         position: int|null,
     *         cleaned_name: string
     *     }>
     * }
     */
    public function match(array $parsedLines, Collection $listings): array
    {
        $matches = [];
        $unmatchedLines = [];
        $usedListingIds = [];

        foreach ($parsedLines as $lineIndex => $parsedLine) {
            if ($parsedLine['normalized_name'] === '') {
                $unmatchedLines[] = $this->formatUnmatchedLine($lineIndex, $parsedLine);

                BatchReorderLogger::step('Step 5.'.($lineIndex + 1), 'No match — empty normalized name', [
                    'raw_line' => $parsedLine['raw_line'],
                ]);

                continue;
            }

            $bestMatch = null;

            if ($parsedLine['listing_reference_id'] !== null) {
                $bestMatch = $this->findListingByReferenceId(
                    $parsedLine['listing_reference_id'],
                    $listings,
                    $usedListingIds
                );
            }

            if ($bestMatch === null) {
                $bestMatch = $this->findBestListingMatch(
                    $parsedLine['normalized_name'],
                    $listings,
                    $usedListingIds
                );
            }

            if ($bestMatch === null) {
                $unmatchedLines[] = $this->formatUnmatchedLine($lineIndex, $parsedLine);

                BatchReorderLogger::step('Step 5.'.($lineIndex + 1), 'No match found for line', [
                    'raw_line' => $parsedLine['raw_line'],
                    'cleaned_name' => $parsedLine['cleaned_name'],
                    'listing_reference_id' => $parsedLine['listing_reference_id'],
                    'normalized_name' => $parsedLine['normalized_name'],
                ]);

                continue;
            }

            $usedListingIds[] = $bestMatch['listing']->id;

            $matches[] = [
                'line_index' => $lineIndex,
                'raw_line' => $parsedLine['raw_line'],
                'position' => $parsedLine['position'],
                'cleaned_name' => $parsedLine['cleaned_name'],
                'order_id' => $bestMatch['listing']->id,
                'listing_name' => (string) ($bestMatch['listing']->make_model ?? ''),
                'match_type' => $bestMatch['match_type'],
                'match_score' => $bestMatch['match_score'],
            ];

            BatchReorderLogger::step('Step 5.'.($lineIndex + 1), 'Matched line to listing', [
                'raw_line' => $parsedLine['raw_line'],
                'position' => $parsedLine['position'],
                'listing_reference_id' => $parsedLine['listing_reference_id'],
                'cleaned_name' => $parsedLine['cleaned_name'],
                'matched_listing_id' => $bestMatch['listing']->id,
                'matched_order_id' => $bestMatch['listing']->order_id ?? null,
                'matched_listing_name' => $bestMatch['listing']->make_model ?? null,
                'match_type' => $bestMatch['match_type'],
                'match_score' => $bestMatch['match_score'],
            ]);
        }

        BatchReorderLogger::step('Step 5 complete', 'Matching finished', [
            'total_matches' => count($matches),
            'total_unmatched_lines' => count($unmatchedLines),
        ]);

        return [
            'matches' => $matches,
            'unmatched_lines' => $unmatchedLines,
        ];
    }

    /**
     * @param  Collection<int, object>  $listings
     * @param  int[]  $excludeListingIds
     * @return array{
     *     listing: object,
     *     match_type: string,
     *     match_score: float
     * }|null
     */
    protected function findListingByReferenceId(int $referenceId, Collection $listings, array $excludeListingIds): ?array
    {
        foreach ($listings as $listing) {
            if (in_array($listing->id, $excludeListingIds, true)) {
                continue;
            }

            if ((int) $listing->id === $referenceId) {
                return [
                    'listing' => $listing,
                    'match_type' => 'listing_id',
                    'match_score' => 100.0,
                ];
            }
        }

        return null;
    }

    /**
     * @param  Collection<int, object>  $listings
     * @param  int[]  $excludeListingIds
     * @return array{
     *     listing: object,
     *     match_type: string,
     *     match_score: float
     * }|null
     */
    protected function findBestListingMatch(string $normalizedName, Collection $listings, array $excludeListingIds): ?array
    {
        $bestMatch = null;
        $comparisons = [];

        foreach ($listings as $listing) {
            if (in_array($listing->id, $excludeListingIds, true)) {
                $comparisons[] = [
                    'listing_id' => $listing->id,
                    'listing_name' => $listing->make_model ?? null,
                    'result' => 'skipped — already matched to another line',
                ];

                continue;
            }

            $listingName = (string) ($listing->make_model ?? '');
            $normalizedListingName = $this->normalizer->normalize($listingName);

            if ($normalizedListingName === '') {
                $comparisons[] = [
                    'listing_id' => $listing->id,
                    'listing_name' => $listingName,
                    'result' => 'skipped — empty listing name',
                ];

                continue;
            }

            if ($normalizedListingName === $normalizedName) {
                $comparisons[] = [
                    'listing_id' => $listing->id,
                    'listing_name' => $listingName,
                    'score' => 100.0,
                    'result' => 'exact match',
                ];

                BatchReorderLogger::step('Step 5 compare', 'Fuzzy matching comparisons for extracted name', [
                    'extracted_normalized_name' => $normalizedName,
                    'comparisons' => $comparisons,
                ]);

                return [
                    'listing' => $listing,
                    'match_type' => 'exact',
                    'match_score' => 100.0,
                ];
            }

            $score = $this->calculateSimilarityScore($normalizedName, $normalizedListingName);

            $comparisons[] = [
                'listing_id' => $listing->id,
                'listing_name' => $listingName,
                'score' => round($score, 2),
                'result' => $score < $this->fuzzyThreshold()
                    ? 'below fuzzy threshold ('.$this->fuzzyThreshold().')'
                    : 'fuzzy candidate',
            ];

            if ($score < $this->fuzzyThreshold()) {
                continue;
            }

            if ($bestMatch === null || $score > $bestMatch['match_score']) {
                $bestMatch = [
                    'listing' => $listing,
                    'match_type' => 'fuzzy',
                    'match_score' => $score,
                ];
            }
        }

        BatchReorderLogger::step('Step 5 compare', 'Fuzzy matching comparisons for extracted name', [
            'extracted_normalized_name' => $normalizedName,
            'comparisons' => $comparisons,
            'best_match_listing_id' => $bestMatch['listing']->id ?? null,
            'best_match_score' => $bestMatch['match_score'] ?? null,
        ]);

        return $bestMatch;
    }

    protected function calculateSimilarityScore(string $needle, string $haystack): float
    {
        if (str_contains($haystack, $needle) || str_contains($needle, $haystack)) {
            $shorter = min(mb_strlen($needle), mb_strlen($haystack));
            $longer = max(mb_strlen($needle), mb_strlen($haystack));

            return $longer > 0 ? ($shorter / $longer) * 100 : 0.0;
        }

        similar_text($needle, $haystack, $percent);

        return (float) $percent;
    }

    protected function fuzzyThreshold(): float
    {
        return 82.0;
    }

    /**
     * @param  array{
     *     raw_line: string,
     *     position: int|null,
     *     cleaned_name: string
     * }  $parsedLine
     * @return array{
     *     line_index: int,
     *     raw_line: string,
     *     position: int|null,
     *     cleaned_name: string
     * }
     */
    protected function formatUnmatchedLine(int $lineIndex, array $parsedLine): array
    {
        return [
            'line_index' => $lineIndex,
            'raw_line' => $parsedLine['raw_line'],
            'position' => $parsedLine['position'],
            'cleaned_name' => $parsedLine['cleaned_name'],
        ];
    }
}
