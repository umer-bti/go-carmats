<?php

namespace App\Services\BatchReorder;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Extracts label ordering from uploaded images using the OpenAI Vision API.
 */
class OpenAiVisionService
{
    public function __construct(
        protected OpenAiLabelResponseParser $labelResponseParser,
        protected ProductNameNormalizer $normalizer,
        protected StructuredLabelGapDetector $gapDetector,
        protected ParsedLineMerger $parsedLineMerger
    ) {
        $this->apiKey = (string) config('openai.api_key');
        $this->baseUrl = rtrim((string) config('openai.base_url'), '/');
        $this->model = (string) config('openai.vision_model');
        $this->timeout = (int) config('openai.timeout');
        $this->maxTokens = (int) config('openai.max_tokens');
        $this->imageDetail = (string) config('openai.image_detail', 'high');
    }

    protected string $apiKey;

    protected string $baseUrl;

    protected string $model;

    protected int $timeout;

    protected int $maxTokens;

    protected string $imageDetail;

    /**
     * Extract structured labels from an image using batch context.
     *
     * @param  Collection<int, object>  $listings
     * @return array{
     *     parsed_lines: array<int, array<string, mixed>>,
     *     raw_response: string,
     *     extraction_mode: string
     * }
     */
    public function extractStructuredLabels(UploadedFile $file, Collection $listings): array
    {
        $structuredResponse = $this->requestStructuredLabels($file, $listings);
        $parsedLines = $this->labelResponseParser->parse($structuredResponse, $listings);

        if ($parsedLines !== []) {
            BatchReorderLogger::step('Step 2c', 'OpenAI structured label extraction completed', [
                'model' => $this->model,
                'file_name' => $file->getClientOriginalName(),
                'extraction_mode' => 'structured_json',
                'labels_found' => count($parsedLines),
                'raw_response' => $structuredResponse,
            ]);

            [$parsedLines, $structuredResponse] = $this->recoverMissingStructuredLabels(
                $file,
                $listings,
                $parsedLines,
                $structuredResponse
            );

            return [
                'parsed_lines' => $parsedLines,
                'raw_response' => $structuredResponse,
                'extraction_mode' => 'structured_json',
            ];
        }

        BatchReorderLogger::step('Step 2c', 'Structured extraction returned no valid labels, falling back to text OCR', [
            'model' => $this->model,
            'file_name' => $file->getClientOriginalName(),
            'raw_response' => $structuredResponse,
        ]);

        $extractedText = $this->extractTextFromImage($file);

        return [
            'parsed_lines' => $this->normalizer->parseLines($extractedText),
            'raw_response' => $extractedText,
            'extraction_mode' => 'text_fallback',
        ];
    }

    /**
     * Extract plain text from an uploaded image file.
     */
    public function extractTextFromImage(UploadedFile $file): string
    {
        if ($this->apiKey === '') {
            throw new RuntimeException('OpenAI API key is not configured. Please set OPENAI_API_KEY in your environment.');
        }

        $mimeType = $file->getMimeType() ?: 'image/jpeg';
        $dataUri = $this->buildDataUri($file, $mimeType);
        $prompt = $this->buildTextExtractionPrompt();

        BatchReorderLogger::step('Step 2b', 'OpenAI Vision text OCR request started', [
            'model' => $this->model,
            'file_name' => $file->getClientOriginalName(),
            'file_path' => $file->getRealPath(),
            'image_dimensions' => $this->describeImageDimensions($file),
            'mime_type' => $mimeType,
            'full_prompt' => $prompt,
        ]);

        $content = $this->postVisionCompletion([
            [
                'role' => 'user',
                'content' => [
                    [
                        'type' => 'text',
                        'text' => $prompt,
                    ],
                    $this->buildImageContent($dataUri),
                ],
            ],
        ], 'text OCR');

        BatchReorderLogger::step('Step 2d', 'OpenAI Vision text OCR completed', [
            'model' => $this->model,
            'file_name' => $file->getClientOriginalName(),
            'extracted_text' => $content,
        ]);

        return $content;
    }

    /**
     * @param  Collection<int, object>  $listings
     */
    protected function requestStructuredLabels(UploadedFile $file, Collection $listings): string
    {
        if ($this->apiKey === '') {
            throw new RuntimeException('OpenAI API key is not configured. Please set OPENAI_API_KEY in your environment.');
        }

        $mimeType = $file->getMimeType() ?: 'image/jpeg';
        $dataUri = $this->buildDataUri($file, $mimeType);
        $prompt = $this->buildStructuredExtractionPrompt($listings);

        BatchReorderLogger::step('Step 2b', 'OpenAI Vision structured request started', [
            'model' => $this->model,
            'file_name' => $file->getClientOriginalName(),
            'file_path' => $file->getRealPath(),
            'image_dimensions' => $this->describeImageDimensions($file),
            'mime_type' => $mimeType,
            'image_detail' => $this->imageDetail,
            'batch_listing_count' => $listings->count(),
            'full_prompt' => $prompt,
        ]);

        return $this->postVisionCompletion([
            [
                'role' => 'system',
                'content' => 'You extract car mat product labels from template layout images. Respond with valid JSON only.',
            ],
            [
                'role' => 'user',
                'content' => [
                    [
                        'type' => 'text',
                        'text' => $prompt,
                    ],
                    $this->buildImageContent($dataUri),
                ],
            ],
        ], 'structured label extraction', jsonResponse: true);
    }

    /**
     * @param  Collection<int, object>  $missingListings
     * @param  array<int, array<string, mixed>>  $extractedLines
     */
    protected function requestRecoveryLabels(UploadedFile $file, Collection $missingListings, array $extractedLines): string
    {
        if ($this->apiKey === '') {
            throw new RuntimeException('OpenAI API key is not configured. Please set OPENAI_API_KEY in your environment.');
        }

        $mimeType = $file->getMimeType() ?: 'image/jpeg';
        $dataUri = $this->buildDataUri($file, $mimeType);
        $prompt = $this->buildRecoveryExtractionPrompt($missingListings, $extractedLines);

        BatchReorderLogger::step('Step 2e', 'OpenAI Vision recovery request started', [
            'model' => $this->model,
            'file_name' => $file->getClientOriginalName(),
            'file_path' => $file->getRealPath(),
            'mime_type' => $mimeType,
            'image_detail' => $this->imageDetail,
            'missing_listing_count' => $missingListings->count(),
            'missing_listings' => $this->gapDetector->toLogContext($missingListings),
            'full_prompt' => $prompt,
        ]);

        return $this->postVisionCompletion([
            [
                'role' => 'system',
                'content' => 'You recover missing car mat product labels from template layout images. Respond with valid JSON only.',
            ],
            [
                'role' => 'user',
                'content' => [
                    [
                        'type' => 'text',
                        'text' => $prompt,
                    ],
                    $this->buildImageContent($dataUri),
                ],
            ],
        ], 'structured label recovery', jsonResponse: true);
    }

    /**
     * @param  array<int, array<string, mixed>>  $parsedLines
     * @param  Collection<int, object>  $listings
     * @return array{0: array<int, array<string, mixed>>, 1: string}
     */
    protected function recoverMissingStructuredLabels(
        UploadedFile $file,
        Collection $listings,
        array $parsedLines,
        string $structuredResponse
    ): array {
        $missingListings = $this->gapDetector->findMissing($parsedLines, $listings);

        BatchReorderLogger::step('Step 2d', 'Gap check after structured extraction', [
            'batch_listing_count' => $listings->count(),
            'extracted_listing_count' => $listings->count() - $missingListings->count(),
            'missing_listing_count' => $missingListings->count(),
            'missing_listings' => $this->gapDetector->toLogContext($missingListings),
        ]);

        if ($missingListings->isEmpty()) {
            return [$parsedLines, $structuredResponse];
        }

        $recoveryResponse = $this->requestRecoveryLabels($file, $missingListings, $parsedLines);
        $recoveredLines = $this->labelResponseParser->parse($recoveryResponse, $listings);
        $mergedLines = $this->parsedLineMerger->merge($parsedLines, $recoveredLines);

        BatchReorderLogger::step('Step 2f', 'OpenAI structured label recovery completed', [
            'model' => $this->model,
            'file_name' => $file->getClientOriginalName(),
            'recovery_labels_found' => count($recoveredLines),
            'total_labels_after_recovery' => count($mergedLines),
            'still_missing_listing_count' => $this->gapDetector->findMissing($mergedLines, $listings)->count(),
            'raw_recovery_response' => $recoveryResponse,
        ]);

        $combinedResponse = json_encode([
            'initial_extraction' => json_decode($structuredResponse, true),
            'recovery_extraction' => json_decode($recoveryResponse, true),
        ], JSON_THROW_ON_ERROR);

        return [$mergedLines, $combinedResponse];
    }

    /**
     * @param  Collection<int, object>  $listings
     */
    protected function buildStructuredExtractionPrompt(Collection $listings): string
    {
        $listingLines = $listings
            ->map(function ($listing) {
                return sprintf('- Listing ID %s: %s', $listing->id, $listing->make_model ?? 'Unknown product');
            })
            ->implode("\n");

        $validListingIds = $listings->pluck('id')->implode(', ');

        return <<<PROMPT
This image is a car mat cutting template sheet. Product labels are printed on the sheet and may be rotated vertically.

Each label uses this format:
POSITION-PRODUCT NAME-LISTING_ID-1

Example:
1-Renault Captur 2020 To Present-99193-1

Important:
- LISTING_ID is the numeric ID from the batch list below (for example 99193).
- Do NOT use Amazon order numbers or any other marketplace order number.
- Valid listing IDs for this batch: {$validListingIds}

The current batch contains ONLY these listings:
{$listingLines}

Your task:
1. Read every visible label in the image.
2. For each label, return:
   - position: the leading number before the first hyphen
   - listing_id: the listing ID number before the final -1 suffix
   - raw_line: the full label text exactly as shown in the image
3. Use only listing_id values from the batch list above.
4. Return labels in the JSON array in visual reading order from top to bottom as they appear on the sheet. Do not sort by position number.
5. Text may be vertical or rotated. Read carefully.
6. If two labels look similar, use listing_id to distinguish them.

Return JSON in this exact shape:
{
  "labels": [
    {
      "position": 1,
      "listing_id": 99193,
      "raw_line": "1-Renault Captur 2020 To Present-99193-1"
    }
  ]
}
PROMPT;
    }

    /**
     * @param  Collection<int, object>  $missingListings
     * @param  array<int, array<string, mixed>>  $extractedLines
     */
    protected function buildRecoveryExtractionPrompt(Collection $missingListings, array $extractedLines): string
    {
        $missingLines = $missingListings
            ->map(function ($listing) {
                return sprintf('- Listing ID %s: %s', $listing->id, $listing->make_model ?? 'Unknown product');
            })
            ->implode("\n");

        $extractedOrderLines = collect($extractedLines)
            ->map(function (array $line, int $index) {
                return sprintf(
                    '%d. Listing ID %s: %s',
                    $index + 1,
                    $line['listing_reference_id'] ?? 'unknown',
                    $line['cleaned_name'] ?? ''
                );
            })
            ->implode("\n");

        return <<<PROMPT
An initial pass over this car mat cutting template sheet found most labels, but the following batch listings were NOT extracted. Search the image again carefully and find ONLY these missing labels.

Missing listings:
{$missingLines}

Labels already extracted, in visual order from top to bottom:
{$extractedOrderLines}

Each label uses this format:
POSITION-PRODUCT NAME-LISTING_ID-1

Example:
4-Hyundai Bayon 2021 to Present-99121-1

Your task:
1. Look specifically for the missing listings listed above.
2. Text may be vertical or rotated. Read carefully.
3. Return only labels you can confidently read for the missing listings.
4. Use only listing_id values from the missing list above.
5. The position number is the leading number before the first hyphen.
6. For each label you find, also report where it sits VISUALLY on the sheet relative to the already-extracted labels:
   - above_listing_id: the listing ID of the already-extracted label that appears immediately ABOVE it on the sheet, or null if it is the topmost label.
   - below_listing_id: the listing ID of the already-extracted label that appears immediately BELOW it on the sheet, or null if it is the bottommost label.
   Use only listing IDs from the already-extracted list for these two fields.

Return JSON in this exact shape:
{
  "labels": [
    {
      "position": 4,
      "listing_id": 99121,
      "raw_line": "4-Hyundai Bayon 2021 to Present-99121-1",
      "above_listing_id": 99208,
      "below_listing_id": 99202
    }
  ]
}

If you cannot find a label, omit it from the response. Do not guess.
PROMPT;
    }

    protected function buildTextExtractionPrompt(): string
    {
        return 'Extract all visible product label text from this image. Each label follows this exact format: POSITION-PRODUCT NAME-ORDER_ID-1. Example: 1-Renault Captur 2020 To Present-99193-1. Return only the extracted labels, one per line, in visual order from top to bottom as they appear on the sheet. Do not sort by position number. Preserve each line exactly as shown, including the leading position number, the full product name, and the trailing order ID suffix such as -99193-1. Text may be rotated vertically. Do not wrap the response in markdown code blocks. Do not add commentary or extra formatting.';
    }

    /**
     * @return array{type: string, image_url: array{url: string, detail: string}}
     */
    protected function buildImageContent(string $dataUri): array
    {
        return [
            'type' => 'image_url',
            'image_url' => [
                'url' => $dataUri,
                'detail' => $this->imageDetail,
            ],
        ];
    }

    /**
     * @return array{width: int, height: int}|null
     */
    protected function describeImageDimensions(UploadedFile $file): ?array
    {
        $path = $file->getRealPath();
        $info = $path ? @getimagesize($path) : false;

        if ($info === false) {
            return null;
        }

        return ['width' => (int) $info[0], 'height' => (int) $info[1]];
    }

    protected function buildDataUri(UploadedFile $file, string $mimeType): string
    {
        $base64Image = base64_encode((string) file_get_contents($file->getRealPath()));

        return sprintf('data:%s;base64,%s', $mimeType, $base64Image);
    }

    /**
     * @param  array<int, array<string, mixed>>  $messages
     */
    protected function postVisionCompletion(array $messages, string $operation, bool $jsonResponse = false): string
    {
        $payload = [
            'model' => $this->model,
            'max_tokens' => $this->maxTokens,
            // Deterministic output: the same image must always produce the
            // same extraction, otherwise re-uploads behave unpredictably.
            'temperature' => 0,
            'messages' => $messages,
        ];

        if ($jsonResponse) {
            $payload['response_format'] = ['type' => 'json_object'];
        }

        $startedAt = microtime(true);

        $response = Http::timeout($this->timeout)
            ->withToken($this->apiKey)
            ->post($this->baseUrl.'/chat/completions', $payload);

        $elapsedMs = round((microtime(true) - $startedAt) * 1000, 2);

        BatchReorderLogger::step('OpenAI timing', 'OpenAI API call completed', [
            'operation' => $operation,
            'model' => $this->model,
            'response_time_ms' => $elapsedMs,
            'http_status' => $response->status(),
            'usage' => data_get($response->json(), 'usage'),
            'finish_reason' => data_get($response->json(), 'choices.0.finish_reason'),
        ]);

        return $this->parseSuccessfulResponse($response, $operation);
    }

    protected function parseSuccessfulResponse($response, string $operation): string
    {
        if (! $response->successful()) {
            $errorBody = $response->json();
            $errorMessage = data_get($errorBody, 'error.message');
            $errorCode = data_get($errorBody, 'error.code');

            Log::error('OpenAI Vision API request failed', [
                'operation' => $operation,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            throw new RuntimeException($this->formatApiErrorMessage($response->status(), $errorCode, $errorMessage));
        }

        $content = data_get($response->json(), 'choices.0.message.content');

        if (! is_string($content) || trim($content) === '') {
            throw new RuntimeException('No text could be extracted from the uploaded image.');
        }

        return trim($content);
    }

    protected function formatApiErrorMessage(int $status, ?string $errorCode, ?string $errorMessage): string
    {
        if ($errorCode === 'insufficient_quota' || $status === 429) {
            return 'OpenAI quota exceeded. Please check your OpenAI plan and billing.';
        }

        if ($status === 401) {
            return 'OpenAI API key is invalid. Please check OPENAI_API_KEY in your environment.';
        }

        if ($errorMessage) {
            return 'OpenAI request failed: '.$errorMessage;
        }

        return 'Failed to extract text from the uploaded image. Please try again.';
    }
}
