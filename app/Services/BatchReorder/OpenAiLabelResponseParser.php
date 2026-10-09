<?php

namespace App\Services\BatchReorder;

use Illuminate\Support\Collection;

/**
 * Parses and validates structured label data returned by OpenAI Vision.
 */
class OpenAiLabelResponseParser
{
    public function __construct(
        protected ProductNameNormalizer $normalizer
    ) {}

    /**
     * @return array<int, array{
     *     raw_line: string,
     *     position: int|null,
     *     listing_reference_id: int|null,
     *     cleaned_name: string,
     *     normalized_name: string,
     *     above_listing_id: int|null,
     *     below_listing_id: int|null,
     *     low_confidence: bool
     * }>
     */
    public function parse(string $content, Collection $listings): array
    {
        $payload = json_decode($content, true);

        if (! is_array($payload)) {
            BatchReorderLogger::step('Parser', 'Response rejected — not valid JSON', [
                'json_error' => json_last_error_msg(),
                'raw_content' => $content,
            ]);

            return [];
        }

        $labels = $payload['labels'] ?? [];
        if (! is_array($labels) || $labels === []) {
            BatchReorderLogger::step('Parser', 'Response rejected — no "labels" array found', [
                'payload_keys' => array_keys($payload),
                'raw_content' => $content,
            ]);

            return [];
        }

        $listingLookup = $this->buildListingLookup($listings);
        $parsedLines = [];

        foreach ($labels as $labelIndex => $label) {
            if (! is_array($label)) {
                BatchReorderLogger::step('Parser', 'Label rejected — not an object', [
                    'label_index' => $labelIndex,
                    'label' => $label,
                ]);

                continue;
            }

            $parsedLine = $this->parseLabel($label, $listingLookup);
            if ($parsedLine !== null) {
                $parsedLines[] = $parsedLine;
            }
        }

        BatchReorderLogger::step('Parser', 'Structured label parsing finished', [
            'labels_in_response' => count($labels),
            'labels_accepted' => count($parsedLines),
            'labels_rejected' => count($labels) - count($parsedLines),
        ]);

        return $parsedLines;
    }

    /**
     * @param  array<string, mixed>  $label
     * @param  array<int, object>  $listingLookup
     * @return array{
     *     raw_line: string,
     *     position: int|null,
     *     listing_reference_id: int|null,
     *     cleaned_name: string,
     *     normalized_name: string,
     *     above_listing_id: int|null,
     *     below_listing_id: int|null,
     *     low_confidence: bool
     * }|null
     */
    protected function parseLabel(array $label, array $listingLookup): ?array
    {
        $rawLine = trim((string) ($label['raw_line'] ?? ''));
        $position = isset($label['position']) ? (int) $label['position'] : null;
        $referenceId = null;
        $lowConfidence = false;

        if ($rawLine !== '' && $this->hasNumberPrefix($rawLine)) {
            [$positionFromLine, $remainder] = $this->normalizer->stripPositionPrefix($rawLine);
            $referenceId = $this->normalizer->extractListingReferenceId($remainder);
            $position = $positionFromLine ?? $position;
        } elseif ($rawLine !== '') {
            // Truncated OCR line (for example "S205) 2014-2021-99123-1"): the
            // position prefix is unreadable, but the structured fields the
            // model reported (position / listing_id) may still be valid.
            // Accept the label from those fields instead of discarding it,
            // flagged as low confidence.
            $referenceId = $this->normalizer->extractListingReferenceId($rawLine);
            $lowConfidence = true;

            BatchReorderLogger::step('Parser', 'raw_line missing numeric position prefix (likely truncated OCR) — falling back to reported fields', [
                'raw_line' => $rawLine,
                'reported_position' => $position,
                'reported_listing_id' => $label['listing_id'] ?? null,
            ]);
        }

        if ($position === null || $position < 1) {
            BatchReorderLogger::step('Parser', 'Label rejected — no valid position', [
                'raw_line' => $rawLine,
                'reported_position' => $label['position'] ?? null,
                'reported_listing_id' => $label['listing_id'] ?? null,
            ]);

            return null;
        }

        foreach (['listing_id', 'order_reference_id'] as $field) {
            if ($referenceId !== null && isset($listingLookup[$referenceId])) {
                break;
            }

            if (! isset($label[$field])) {
                continue;
            }

            $candidateId = (int) $label[$field];

            if (isset($listingLookup[$candidateId])) {
                $referenceId = $candidateId;
            }
        }

        if ($referenceId === null || ! isset($listingLookup[$referenceId])) {
            BatchReorderLogger::step('Parser', 'Label rejected — listing ID not found in batch', [
                'raw_line' => $rawLine,
                'position' => $position,
                'extracted_reference_id' => $referenceId,
                'reported_listing_id' => $label['listing_id'] ?? null,
                'valid_listing_ids' => array_keys($listingLookup),
            ]);

            return null;
        }

        $listing = $listingLookup[$referenceId];
        $cleanedName = (string) ($listing->make_model ?? '');

        if ($rawLine === '') {
            $rawLine = sprintf(
                '%d-%s-%d-1',
                $position,
                $cleanedName,
                $referenceId
            );
        }

        return [
            'raw_line' => $rawLine,
            'position' => $position,
            'listing_reference_id' => $referenceId,
            'cleaned_name' => $cleanedName,
            'normalized_name' => $this->normalizer->normalize($cleanedName),
            'above_listing_id' => $this->extractAnchorId($label, 'above_listing_id'),
            'below_listing_id' => $this->extractAnchorId($label, 'below_listing_id'),
            'low_confidence' => $lowConfidence,
        ];
    }

    /**
     * Read a visual-anchor listing ID reported by the recovery pass.
     *
     * @param  array<string, mixed>  $label
     */
    protected function extractAnchorId(array $label, string $field): ?int
    {
        if (! isset($label[$field]) || ! is_numeric($label[$field])) {
            return null;
        }

        $id = (int) $label[$field];

        return $id > 0 ? $id : null;
    }

    protected function hasNumberPrefix(string $line): bool
    {
        return preg_match('/^\d+-/', trim($line)) === 1;
    }

    /**
     * @return array<int, object>
     */
    protected function buildListingLookup(Collection $listings): array
    {
        $lookup = [];

        foreach ($listings as $listing) {
            $lookup[(int) $listing->id] = $listing;
        }

        return $lookup;
    }
}
