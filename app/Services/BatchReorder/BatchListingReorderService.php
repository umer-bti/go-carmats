<?php

namespace App\Services\BatchReorder;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use RuntimeException;

/**
 * Coordinates OCR/text extraction, parsing, matching, and listing reordering.
 */
class BatchListingReorderService
{
    public function __construct(
        protected OpenAiVisionService $visionService,
        protected ProductNameNormalizer $normalizer,
        protected ProductListingMatcher $matcher,
        protected ParsedLineMatchBuilder $parsedLineMatchBuilder,
        protected ListingReorderService $reorderService
    ) {}

    /**
     * Process an uploaded file and return the reordered listing IDs plus summary.
     *
     * @param  Collection<int, object>  $listings
     * @return array{
     *     extracted_text: string,
     *     ordered_ids: int[],
     *     summary: array<string, mixed>,
     *     matches: array<int, array<string, mixed>>
     * }
     */
    public function process(UploadedFile $file, Collection $listings, ?int $batchId = null): array
    {
        $pipelineStartedAt = microtime(true);

        BatchReorderLogger::step('Step 1', 'Upload received', [
            'batch_id' => $batchId,
            'file_name' => $file->getClientOriginalName(),
            'file_path' => $file->getRealPath(),
            'file_type' => $file->getClientMimeType(),
            'file_size_kb' => round($file->getSize() / 1024, 2),
        ]);

        BatchReorderLogger::step('Step 2', 'Current batch listings before reorder', [
            'listings' => $listings->map(fn ($listing) => [
                'id' => $listing->id,
                'order_id' => $listing->order_id ?? null,
                'make_model' => $listing->make_model ?? null,
            ])->values()->all(),
        ]);

        [$extractedText, $parsedLines, $extractionMode] = $this->extractAndParse($file, $listings);

        BatchReorderLogger::step('Step 3', 'Extraction completed', [
            'extraction_mode' => $extractionMode,
            'extracted_text' => $extractedText,
        ]);

        BatchReorderLogger::step('Step 4', 'Parsed lines from extracted text', [
            'total_lines' => count($parsedLines),
        ]);

        foreach ($parsedLines as $index => $parsedLine) {
            BatchReorderLogger::step('Step 4.'.($index + 1), 'Parsed line', [
                'raw_line' => $parsedLine['raw_line'],
                'position' => $parsedLine['position'],
                'listing_reference_id' => $parsedLine['listing_reference_id'],
                'cleaned_name' => $parsedLine['cleaned_name'],
                'normalized_name' => $parsedLine['normalized_name'],
            ]);
        }

        $currentOrderIds = $listings->pluck('id')->map(fn ($id) => (int) $id)->values()->all();

        BatchReorderLogger::step('Step 5', 'Matching parsed lines to batch listings');

        $matchResult = $this->parsedLineMatchBuilder->build($parsedLines, $listings);

        if ($matchResult['matches'] === []) {
            $matchResult = $this->matcher->match($parsedLines, $listings);
        } else {
            foreach ($matchResult['matches'] as $index => $match) {
                BatchReorderLogger::step('Step 5.'.($index + 1), 'Matched line to listing', [
                    'raw_line' => $match['raw_line'],
                    'position' => $match['position'],
                    'listing_reference_id' => $parsedLines[$match['line_index']]['listing_reference_id'] ?? null,
                    'matched_listing_id' => $match['order_id'],
                    'matched_listing_name' => $match['listing_name'],
                    'match_type' => $match['match_type'],
                    'match_score' => $match['match_score'],
                ]);
            }

            BatchReorderLogger::step('Step 5 complete', 'Matching finished', [
                'total_matches' => count($matchResult['matches']),
                'total_unmatched_lines' => count($matchResult['unmatched_lines']),
            ]);
        }

        BatchReorderLogger::step('Step 6', 'Building final order from matches');

        $reorderResult = $this->reorderService->reorder(
            $currentOrderIds,
            $matchResult['matches'],
            $matchResult['unmatched_lines'],
            count($parsedLines),
            $listings
        );

        BatchReorderLogger::step('Step 7', 'Pipeline complete', [
            'ordered_ids' => $reorderResult['ordered_ids'],
            'summary' => $reorderResult['summary'],
            'total_execution_time_ms' => round((microtime(true) - $pipelineStartedAt) * 1000, 2),
        ]);

        return [
            'extracted_text' => $extractedText,
            'ordered_ids' => $reorderResult['ordered_ids'],
            'summary' => $reorderResult['summary'],
            'matches' => $matchResult['matches'],
        ];
    }

    /**
     * @param  Collection<int, object>  $listings
     * @return array{0: string, 1: array<int, array<string, mixed>>, 2: string}
     */
    protected function extractAndParse(UploadedFile $file, Collection $listings): array
    {
        $extension = strtolower($file->getClientOriginalExtension());

        if ($extension === 'txt') {
            BatchReorderLogger::step('Step 2a', 'Reading text file directly');

            $content = trim((string) file_get_contents($file->getRealPath()));

            if ($content === '') {
                throw new RuntimeException('The uploaded text file is empty.');
            }

            return [$content, $this->normalizer->parseLines($content), 'text_file'];
        }

        BatchReorderLogger::step('Step 2a', 'Sending image to OpenAI Vision with batch context');

        $result = $this->visionService->extractStructuredLabels($file, $listings);

        return [
            $result['raw_response'],
            $result['parsed_lines'],
            $result['extraction_mode'],
        ];
    }
}
