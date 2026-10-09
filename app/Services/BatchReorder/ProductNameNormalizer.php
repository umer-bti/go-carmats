<?php

namespace App\Services\BatchReorder;

/**
 * Normalizes and parses product names extracted from OCR or text uploads.
 */
class ProductNameNormalizer
{
    /**
     * Clean raw OCR/text output before line parsing.
     */
    public function sanitizeExtractedText(string $content): string
    {
        $content = trim($content);
        $content = preg_replace('/^```(?:\w+)?\s*/', '', $content) ?? $content;
        $content = preg_replace('/\s*```$/', '', $content) ?? $content;

        return trim($content);
    }

    /**
     * Split raw OCR/text content into product lines.
     *
     * @return string[]
     */
    public function extractLines(string $content): array
    {
        $content = $this->sanitizeExtractedText($content);
        $lines = preg_split('/\R+/', $content) ?: [];

        return array_values(array_filter(
            array_map('trim', $lines),
            fn (string $line) => $line !== '' && $this->isProductLine($line)
        ));
    }

    /**
     * Parse a single line into position, listing reference, and cleaned product name.
     *
     * @return array{
     *     raw_line: string,
     *     position: int|null,
     *     listing_reference_id: int|null,
     *     cleaned_name: string,
     *     normalized_name: string
     * }
     */
    public function parseLine(string $line): array
    {
        $rawLine = trim($line);
        [$position, $remainder] = $this->stripPositionPrefix($rawLine);
        $listingReferenceId = $this->extractListingReferenceId($remainder);
        $cleanedName = $this->cleanProductName($remainder);

        return [
            'raw_line' => $rawLine,
            'position' => $position,
            'listing_reference_id' => $listingReferenceId,
            'cleaned_name' => $cleanedName,
            'normalized_name' => $this->normalize($cleanedName),
        ];
    }

    /**
     * Parse every product line from OCR/text content.
     *
     * @return array<int, array{
     *     raw_line: string,
     *     position: int|null,
     *     listing_reference_id: int|null,
     *     cleaned_name: string,
     *     normalized_name: string
     * }>
     */
    public function parseLines(string $content): array
    {
        return array_map(
            fn (string $line) => $this->parseLine($line),
            $this->extractLines($content)
        );
    }

    /**
     * Remove an optional leading position prefix such as "10-" or "2-".
     *
     * @return array{0: int|null, 1: string}
     */
    public function stripPositionPrefix(string $line): array
    {
        if (preg_match('/^(\d+)-(.+)$/', trim($line), $matches) === 1) {
            return [(int) $matches[1], trim($matches[2])];
        }

        return [null, trim($line)];
    }

    /**
     * Extract the listing/order reference ID from a suffix such as "-99193-1".
     */
    public function extractListingReferenceId(string $text): ?int
    {
        if (preg_match('/-(\d{4,6})-\d+$/', trim($text), $matches) === 1) {
            return (int) $matches[1];
        }

        return null;
    }

    /**
     * Remove only the trailing SKU suffix (for example "-99193-1") and normalize spacing.
     */
    public function cleanProductName(string $text): string
    {
        $name = trim($text);
        $name = preg_replace('/-\d{4,6}-\d+$/', '', $name) ?? $name;
        $name = str_replace('_', ' ', $name);
        $name = preg_replace('/\s+/', ' ', $name) ?? $name;

        return trim($name);
    }

    /**
     * Normalize a product name for case-insensitive comparison.
     */
    public function normalize(string $text): string
    {
        $normalized = str_replace('_', ' ', trim($text));
        $normalized = preg_replace('/\s+/', ' ', $normalized) ?? $normalized;

        return mb_strtolower($normalized);
    }

    protected function isProductLine(string $line): bool
    {
        return preg_match('/^\d+-.+-\d{4,6}-\d+$/', $line) === 1;
    }
}
