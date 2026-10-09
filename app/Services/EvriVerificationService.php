<?php

namespace App\Services;

use App\Models\EvriSetting;
use App\Models\EvriShipmentVerification;
use App\Models\Order;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class EvriVerificationService
{
    public function snapshotSystemState(Order $order): array
    {
        $order->loadMissing('files');

        return [
            'system_status' => $order->status,
            'system_shipped' => $order->status === 'shipped',
            'system_has_label' => $order->hasSavedLabel(),
            'system_tracking_number' => $order->tracking_number,
        ];
    }

    public function recordLabelCreated(Order $order, string $barcodeNumber): EvriShipmentVerification
    {
        $system = $this->snapshotSystemState($order);

        return EvriShipmentVerification::updateOrCreate(
            ['order_id' => $order->id],
            array_merge($system, [
                'evri_label_exists' => true,
                'evri_status' => 'Label Created',
                'evri_status_detail' => 'Parcel registered via routeDeliveryCreatePreadviceAndLabel.',
                'verification_result' => $this->resolveVerificationResult($system, true, 'Label Created'),
                'evri_verified_at' => now(),
                'evri_error' => null,
                'system_tracking_number' => $barcodeNumber ?: $system['system_tracking_number'],
            ])
        );
    }

    /**
     * Orders shipped via Evri that need a fresh Evri API confirmation.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, Order>
     */
    public function ordersDueForVerification(int $limit): \Illuminate\Database\Eloquent\Collection
    {
        $recheckBefore = now()->subHours((int) config('evri.verification_recheck_hours', 6));

        return Order::query()
            ->with(['files', 'evriShipmentVerification'])
            ->where('generated_by', 'evri')
            ->whereNotNull('tracking_number')
            ->where('tracking_number', '!=', '')
            ->where(function ($query) use ($recheckBefore) {
                $query->whereDoesntHave('evriShipmentVerification')
                    ->orWhereHas('evriShipmentVerification', function ($verificationQuery) use ($recheckBefore) {
                        $verificationQuery
                            ->whereIn('verification_result', ['pending', 'error'])
                            ->orWhere(function ($staleQuery) use ($recheckBefore) {
                                $staleQuery
                                    ->where('verification_result', 'verified')
                                    ->where(function ($timeQuery) use ($recheckBefore) {
                                        $timeQuery
                                            ->whereNull('evri_verified_at')
                                            ->orWhere('evri_verified_at', '<=', $recheckBefore);
                                    });
                            })
                            ->orWhere(function ($staleQuery) use ($recheckBefore) {
                                $staleQuery
                                    ->where('verification_result', 'mismatch')
                                    ->where(function ($timeQuery) use ($recheckBefore) {
                                        $timeQuery
                                            ->whereNull('evri_verified_at')
                                            ->orWhere('evri_verified_at', '<=', $recheckBefore);
                                    });
                            });
                    });
            })
            ->orderBy('id')
            ->limit($limit)
            ->get();
    }

    /**
     * @return array{processed: int, verified: int, mismatch: int, errors: int}
     */
    public function verifyDueOrders(?int $limit = null): array
    {
        $limit = $limit ?? (int) config('evri.verification_batch_limit', 100);
        $counts = [
            'processed' => 0,
            'verified' => 0,
            'mismatch' => 0,
            'errors' => 0,
        ];

        foreach ($this->ordersDueForVerification($limit) as $order) {
            $result = $this->verifyOrder($order);
            $counts['processed']++;

            match ($result->verification_result) {
                'verified' => $counts['verified']++,
                'mismatch' => $counts['mismatch']++,
                'error' => $counts['errors']++,
                default => null,
            };
        }

        return $counts;
    }

    public function verifyOrder(Order $order): EvriShipmentVerification
    {
        $order->loadMissing('files');
        $system = $this->snapshotSystemState($order);

        if ($order->generated_by !== 'evri') {
            return EvriShipmentVerification::updateOrCreate(
                ['order_id' => $order->id],
                array_merge($system, [
                    'evri_label_exists' => null,
                    'evri_status' => null,
                    'evri_status_detail' => null,
                    'verification_result' => 'error',
                    'evri_verified_at' => now(),
                    'evri_error' => 'Order was not shipped via Evri (generated_by is not evri).',
                ])
            );
        }

        if (empty($order->tracking_number)) {
            return EvriShipmentVerification::updateOrCreate(
                ['order_id' => $order->id],
                array_merge($system, [
                    'evri_label_exists' => false,
                    'evri_status' => 'No Tracking',
                    'verification_result' => 'mismatch',
                    'evri_verified_at' => now(),
                    'evri_error' => 'No tracking number on order.',
                ])
            );
        }

        $evri = EvriSetting::getActive();
        if (! $evri) {
            return EvriShipmentVerification::updateOrCreate(
                ['order_id' => $order->id],
                array_merge($system, [
                    'verification_result' => 'error',
                    'evri_verified_at' => now(),
                    'evri_error' => 'No active Evri account configured.',
                ])
            );
        }

        $lookup = $this->lookupEvriTrackingWithDebug($order->tracking_number);
        $evriResult = $lookup['result'];

        $statusDetail = $evriResult['detail'];
        if (! empty($lookup['debug']['attempts']) && ! empty($evriResult['error'])) {
            $statusDetail = json_encode($lookup['debug']['attempts'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            $statusDetail = $statusDetail !== false ? substr($statusDetail, 0, 65000) : $evriResult['detail'];
        }

        return EvriShipmentVerification::updateOrCreate(
            ['order_id' => $order->id],
            array_merge($system, [
                'evri_label_exists' => $evriResult['label_exists'],
                'evri_status' => $evriResult['status'],
                'evri_status_detail' => $statusDetail,
                'verification_result' => $this->resolveVerificationResult(
                    $system,
                    $evriResult['label_exists'],
                    $evriResult['status'],
                    $evriResult['error']
                ),
                'evri_verified_at' => now(),
                'evri_error' => $evriResult['error'],
            ])
        );
    }

    private function resolveVerificationResult(
        array $system,
        ?bool $evriLabelExists,
        ?string $evriStatus,
        ?string $error = null
    ): string {
        if ($error && $evriLabelExists === null) {
            return 'error';
        }

        $systemOk = $system['system_shipped']
            && $system['system_has_label']
            && ! empty($system['system_tracking_number']);

        $evriOk = $evriLabelExists === true;

        if ($systemOk && $evriOk) {
            return 'verified';
        }

        if ($systemOk && $evriLabelExists === false) {
            return 'mismatch';
        }

        if (! $systemOk && $evriOk) {
            return 'mismatch';
        }

        return 'mismatch';
    }

    /**
     * @return list<string>
     */
    public static function barcodeVariants(string $barcode): array
    {
        $barcode = trim($barcode);
        $noSpaces = preg_replace('/\s+/', '', $barcode) ?? $barcode;
        $noDashes = str_replace('-', '', $barcode);
        $compact = str_replace('-', '', $noSpaces);

        return array_values(array_unique(array_filter([
            $barcode,
            $noSpaces,
            $noDashes,
            $compact,
            strtoupper($barcode),
            strtoupper($compact),
        ])));
    }

    /**
     * @return array{label_exists: ?bool, status: ?string, detail: ?string, error: ?string}
     */
    public function lookupEvriTracking(string $barcode): array
    {
        $evri = EvriSetting::getActive();
        if (! $evri) {
            return [
                'label_exists' => null,
                'status' => null,
                'detail' => null,
                'error' => 'No active Evri account configured.',
            ];
        }

        foreach (self::barcodeVariants($barcode) as $variant) {
            $result = $this->fetchEvriParcelStatus($variant, $evri);
            if ($result['label_exists'] !== null || $result['status'] !== null || ($result['error'] && $result['label_exists'] === false)) {
                return $result;
            }
        }

        return $this->fetchEvriParcelStatus($barcode, $evri);
    }

    /**
     * @return array{
     *     result: array{label_exists: ?bool, status: ?string, detail: ?string, error: ?string},
     *     barcode_used: ?string,
     *     debug: array{attempts: list<array<string, mixed>>, barcode_variants: list<string>}
     * }
     */
    public function lookupEvriTrackingWithDebug(string $barcode): array
    {
        $variants = self::barcodeVariants($barcode);
        $attempts = [];

        $evri = EvriSetting::getActive();
        if (! $evri) {
            return [
                'result' => [
                    'label_exists' => null,
                    'status' => null,
                    'detail' => null,
                    'error' => 'No active Evri account configured.',
                ],
                'barcode_used' => null,
                'error_summary' => 'No active Evri account configured.',
                'debug' => [
                    'tracking_endpoints' => config('evri.tracking_endpoints', []),
                    'barcode_variants' => $variants,
                    'attempts' => $attempts,
                ],
            ];
        }

        foreach ($variants as $variant) {
            foreach (config('evri.tracking_endpoints', []) as $endpoint) {
                $attempt = $this->probeTrackingEndpoint($endpoint, $variant, $evri);
                $attempts[] = $attempt;

                if ($attempt['success'] && is_array($attempt['parsed'])) {
                    $parsed = $attempt['parsed'];
                    if ($parsed['label_exists'] !== null || $parsed['status'] !== null) {
                        return [
                            'result' => $parsed,
                            'barcode_used' => $variant,
                            'error_summary' => null,
                            'debug' => [
                                'tracking_endpoints' => config('evri.tracking_endpoints', []),
                                'barcode_variants' => $variants,
                                'attempts' => $attempts,
                            ],
                        ];
                    }
                }
            }
        }

        $errorSummary = $this->summarizeTrackingAttempts($attempts);

        return [
            'result' => [
                'label_exists' => null,
                'status' => null,
                'detail' => null,
                'error' => $errorSummary,
            ],
            'barcode_used' => null,
            'error_summary' => $errorSummary,
            'debug' => [
                'tracking_endpoints' => config('evri.tracking_endpoints', []),
                'barcode_variants' => $variants,
                'attempts' => $attempts,
            ],
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $attempts
     */
    private function summarizeTrackingAttempts(array $attempts): string
    {
        if ($attempts === []) {
            return 'No Evri tracking endpoints are configured in config/evri.php.';
        }

        $parts = [];
        foreach ($attempts as $attempt) {
            $path = parse_url((string) ($attempt['endpoint'] ?? ''), PHP_URL_PATH)
                ?: (string) ($attempt['endpoint'] ?? 'unknown');
            $barcode = (string) ($attempt['barcode'] ?? '');

            if (! empty($attempt['exception'])) {
                $class = ! empty($attempt['exception_class']) ? " ({$attempt['exception_class']})" : '';
                $parts[] = "{$path} [{$barcode}]: {$attempt['exception']}{$class}";
                continue;
            }

            $status = $attempt['http_status'] ?? 'no response';
            $snippet = '';

            if (is_array($attempt['parsed'] ?? null) && ! empty($attempt['parsed']['error'])) {
                $snippet = ' — ' . $attempt['parsed']['error'];
            } elseif (! empty($attempt['body_preview'])) {
                $body = trim(preg_replace('/\s+/', ' ', strip_tags((string) $attempt['body_preview'])));
                if ($body !== '') {
                    $snippet = ' — ' . substr($body, 0, 200);
                }
            }

            $parts[] = "{$path} [{$barcode}]: HTTP {$status}{$snippet}";
        }

        return implode(' | ', $parts);
    }

    /**
     * @return array{label_exists: ?bool, status: ?string, detail: ?string, error: ?string}
     */
    private function fetchEvriParcelStatus(string $barcode, EvriSetting $evri): array
    {
        $endpoints = config('evri.tracking_endpoints', []);

        foreach ($endpoints as $endpoint) {
            $result = $this->requestTrackingEndpoint($endpoint, $barcode, $evri);
            if ($result !== null) {
                return $result;
            }
        }

        $publicResult = $this->requestPublicTracking($barcode, $evri);
        if ($publicResult !== null) {
            return $publicResult;
        }

        return [
            'label_exists' => null,
            'status' => null,
            'detail' => null,
            'error' => 'Could not reach Evri tracking API. Ask your Evri account manager for the correct tracking endpoint.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function probeTrackingEndpoint(string $url, string $barcode, EvriSetting $evri): array
    {
        try {
            $xml = $this->buildTrackingXml($barcode, $evri);

            $response = Http::withHeaders([
                'Content-Type' => 'text/xml',
            ])->withBasicAuth($evri->api_key, $evri->api_secret)
                ->timeout(20)
                ->send('POST', $url, ['body' => $xml]);

            $parsed = $response->ok() ? $this->parseTrackingResponse($response->body()) : null;

            if (! $response->ok()) {
                Log::warning('Evri tracking endpoint failed', [
                    'url' => $url,
                    'barcode' => $barcode,
                    'status' => $response->status(),
                    'body' => substr($response->body(), 0, 500),
                ]);
            }

            return [
                'endpoint' => $url,
                'barcode' => $barcode,
                'http_status' => $response->status(),
                'success' => $response->ok(),
                'body_preview' => substr($response->body(), 0, 1000),
                'parsed' => $parsed,
                'failure_reason' => $response->ok()
                    ? null
                    : 'HTTP ' . $response->status() . ' ' . $response->reason(),
                'exception' => null,
                'exception_class' => null,
            ];
        } catch (\Throwable $e) {
            Log::warning('Evri tracking endpoint exception', [
                'url' => $url,
                'barcode' => $barcode,
                'message' => $e->getMessage(),
                'class' => $e::class,
            ]);

            return [
                'endpoint' => $url,
                'barcode' => $barcode,
                'http_status' => null,
                'success' => false,
                'body_preview' => null,
                'parsed' => null,
                'failure_reason' => $e->getMessage(),
                'exception' => $e->getMessage(),
                'exception_class' => $e::class,
            ];
        }
    }

    /**
     * @return array{label_exists: ?bool, status: ?string, detail: ?string, error: ?string}|null
     */
    private function requestTrackingEndpoint(string $url, string $barcode, EvriSetting $evri): ?array
    {
        try {
            $xml = $this->buildTrackingXml($barcode, $evri);

            $response = Http::withHeaders([
                'Content-Type' => 'text/xml',
            ])->withBasicAuth($evri->api_key, $evri->api_secret)
                ->timeout(20)
                ->send('POST', $url, ['body' => $xml]);

            if (! $response->ok()) {
                Log::warning('Evri tracking endpoint failed', [
                    'url' => $url,
                    'status' => $response->status(),
                    'body' => substr($response->body(), 0, 500),
                ]);

                return null;
            }

            return $this->parseTrackingResponse($response->body());
        } catch (\Throwable $e) {
            Log::warning('Evri tracking endpoint exception', [
                'url' => $url,
                'message' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * @return array{label_exists: ?bool, status: ?string, detail: ?string, error: ?string}|null
     */
    private function requestPublicTracking(string $barcode, EvriSetting $evri): ?array
    {
        $template = config('evri.public_tracking_url');
        if (! $template) {
            return null;
        }

        $url = str_replace(['{barcode}', '{postcode}'], [
            urlencode($barcode),
            urlencode(''),
        ], $template);

        try {
            $response = Http::timeout(15)
                ->acceptJson()
                ->get($url);

            if (! $response->ok()) {
                return null;
            }

            $body = $response->json();
            if (! is_array($body)) {
                return null;
            }

            $status = $this->extractStatusFromArray($body) ?? 'Found';

            return [
                'label_exists' => true,
                'status' => $status,
                'detail' => json_encode($body),
                'error' => null,
            ];
        } catch (\Throwable $e) {
            Log::warning('Evri public tracking failed', ['message' => $e->getMessage()]);

            return null;
        }
    }

    private function buildTrackingXml(string $barcode, EvriSetting $evri): string
    {
        $clientId = htmlspecialchars($evri->client_id ?? '178', ENT_XML1 | ENT_COMPAT, 'UTF-8');
        $clientName = htmlspecialchars($evri->client_name ?? '', ENT_XML1 | ENT_COMPAT, 'UTF-8');
        $childClientId = htmlspecialchars($evri->child_client_id ?? '', ENT_XML1 | ENT_COMPAT, 'UTF-8');
        $childClientName = htmlspecialchars($evri->child_client_name ?? '', ENT_XML1 | ENT_COMPAT, 'UTF-8');
        $barcodeXml = htmlspecialchars($barcode, ENT_XML1 | ENT_COMPAT, 'UTF-8');
        $creationDate = now()->format('Y-m-d\TH:i:s');

        return '<parcelTrackingRequest>'
            . "<clientId>{$clientId}</clientId>"
            . "<clientName>{$clientName}</clientName>"
            . "<childClientId>{$childClientId}</childClientId>"
            . "<childClientName>{$childClientName}</childClientName>"
            . "<creationDate>{$creationDate}</creationDate>"
            . "<barcodeNumber>{$barcodeXml}</barcodeNumber>"
            . '</parcelTrackingRequest>';
    }

    /**
     * @return array{label_exists: ?bool, status: ?string, detail: ?string, error: ?string}
     */
    private function parseTrackingResponse(string $xmlBody): array
    {
        $simple = @simplexml_load_string($xmlBody);
        if ($simple === false) {
            return [
                'label_exists' => null,
                'status' => null,
                'detail' => substr($xmlBody, 0, 1000),
                'error' => 'Failed to parse Evri tracking XML response.',
            ];
        }

        $errorNodes = $simple->xpath('//*[local-name()="errorMessage" or local-name()="error" or local-name()="errors"]');
        if (! empty($errorNodes)) {
            $errorText = trim((string) $errorNodes[0]);
            if ($errorText !== '') {
                $notFound = stripos($errorText, 'not found') !== false
                    || stripos($errorText, 'invalid') !== false;

                return [
                    'label_exists' => $notFound ? false : null,
                    'status' => $notFound ? 'Not Found' : null,
                    'detail' => substr($xmlBody, 0, 1000),
                    'error' => $errorText,
                ];
            }
        }

        $status = null;
        foreach ([
            'parcelStatus', 'deliveryStatus', 'status', 'currentStatus',
            'lastEvent', 'lastEventDescription', 'trackingStatus',
        ] as $field) {
            $nodes = $simple->xpath('//*[local-name()="' . $field . '"]');
            if (! empty($nodes) && trim((string) $nodes[0]) !== '') {
                $status = trim((string) $nodes[0]);
                break;
            }
        }

        if ($status === null) {
            $status = 'Found';
        }

        return [
            'label_exists' => true,
            'status' => $status,
            'detail' => substr($xmlBody, 0, 2000),
            'error' => null,
        ];
    }

    private function extractStatusFromArray(array $data): ?string
    {
        foreach (['status', 'deliveryStatus', 'parcelStatus', 'currentStatus', 'tag'] as $key) {
            if (! empty($data[$key]) && is_string($data[$key])) {
                return $data[$key];
            }
        }

        if (isset($data['checkpoints']) && is_array($data['checkpoints']) && count($data['checkpoints']) > 0) {
            $last = end($data['checkpoints']);
            if (is_array($last)) {
                return $last['message'] ?? $last['status'] ?? $last['tag'] ?? null;
            }
        }

        return null;
    }
}
