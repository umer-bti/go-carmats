<?php

namespace App\Services\BatchReorder;

/**
 * Merges parsed label lines from multiple extraction passes without duplicates.
 *
 * Recovered labels are placed using the visual anchors reported by the
 * recovery pass (above_listing_id / below_listing_id). Position numbers are
 * only trusted for placement when the already-extracted lines prove that
 * position order follows visual order on this sheet — on most real template
 * sheets it does not (e.g. 14, 30, 10, 24, ...).
 */
class ParsedLineMerger
{
    /**
     * @param  array<int, array<string, mixed>>  $existingLines
     * @param  array<int, array<string, mixed>>  $additionalLines
     * @return array<int, array<string, mixed>>
     */
    public function merge(array $existingLines, array $additionalLines): array
    {
        $merged = $existingLines;
        $seenListingIds = collect($existingLines)
            ->pluck('listing_reference_id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->all();

        $positionsAreVisual = $this->positionsFollowVisualOrder($existingLines);

        $linesToInsert = collect($additionalLines)
            ->filter(function (array $line) use ($seenListingIds) {
                $listingId = $line['listing_reference_id'] ?? null;

                return $listingId !== null && ! in_array((int) $listingId, $seenListingIds, true);
            })
            ->values()
            ->all();

        foreach ($linesToInsert as $line) {
            $listingId = (int) $line['listing_reference_id'];
            [$insertAt, $placementMethod] = $this->resolveInsertionIndex($merged, $line, $positionsAreVisual);

            array_splice($merged, $insertAt, 0, [$line]);
            $seenListingIds[] = $listingId;

            BatchReorderLogger::step('Step 2f.1', 'Inserted recovered label into extraction order', [
                'listing_reference_id' => $listingId,
                'position' => $line['position'] ?? null,
                'above_listing_id' => $line['above_listing_id'] ?? null,
                'below_listing_id' => $line['below_listing_id'] ?? null,
                'placement_method' => $placementMethod,
                'insert_at_index' => $insertAt,
                'raw_line' => $line['raw_line'],
            ]);
        }

        return $merged;
    }

    /**
     * @param  array<int, array<string, mixed>>  $lines
     * @param  array<string, mixed>  $line
     * @return array{0: int, 1: string} insertion index and placement method used
     */
    protected function resolveInsertionIndex(array $lines, array $line, bool $positionsAreVisual): array
    {
        $aboveId = $line['above_listing_id'] ?? null;
        $belowId = $line['below_listing_id'] ?? null;

        if ($aboveId !== null) {
            $index = $this->findLineIndexByListingId($lines, (int) $aboveId);

            if ($index !== null) {
                return [$index + 1, 'visual_anchor_above'];
            }
        }

        if ($belowId !== null) {
            $index = $this->findLineIndexByListingId($lines, (int) $belowId);

            if ($index !== null) {
                return [$index, 'visual_anchor_below'];
            }
        }

        $position = $line['position'] ?? null;

        if ($positionsAreVisual && $position !== null) {
            return [$this->resolveIndexByPosition($lines, (int) $position), 'position_number'];
        }

        // No reliable placement information: append at the end rather than
        // guessing a wrong slot in the middle of the sequence.
        return [count($lines), 'appended_at_end'];
    }

    /**
     * Position numbers are only a valid placement signal when the lines the
     * model has already read in visual order carry strictly increasing
     * position numbers.
     *
     * @param  array<int, array<string, mixed>>  $lines
     */
    protected function positionsFollowVisualOrder(array $lines): bool
    {
        $previous = null;

        foreach ($lines as $line) {
            $position = $line['position'] ?? null;

            if ($position === null) {
                return false;
            }

            if ($previous !== null && $position <= $previous) {
                return false;
            }

            $previous = $position;
        }

        return $previous !== null;
    }

    /**
     * @param  array<int, array<string, mixed>>  $lines
     */
    protected function findLineIndexByListingId(array $lines, int $listingId): ?int
    {
        foreach ($lines as $index => $line) {
            if ((int) ($line['listing_reference_id'] ?? 0) === $listingId) {
                return $index;
            }
        }

        return null;
    }

    /**
     * Insert before the first line whose position exceeds the given one.
     * Only used when positions provably follow visual order.
     *
     * @param  array<int, array<string, mixed>>  $lines
     */
    protected function resolveIndexByPosition(array $lines, int $position): int
    {
        foreach ($lines as $index => $line) {
            $linePosition = $line['position'] ?? null;

            if ($linePosition !== null && $linePosition > $position) {
                return $index;
            }
        }

        return count($lines);
    }
}
