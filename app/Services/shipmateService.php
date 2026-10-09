<?php

namespace App\Services;

use App\Models\Order;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class shipmateService
{
    protected string $apiKey;

    protected string $token;

    protected string $baseUrl;

    public function __construct()
    {
        $this->apiKey = (string) config('shipmate.api_key');
        $this->token = (string) config('shipmate.token');
        $this->baseUrl = rtrim((string) config('shipmate.base_url'), '/');
    }

    public function generateLabel(Order $order)
    {
        $configError = $this->shipmateConfigError();
        if ($configError !== null) {
            return $configError;
        }

        $payload = $this->buildShipmentPayload($order);

        $parcelResult = $this->requestShipmentLabel($payload);
        if ($parcelResult instanceof \Illuminate\Http\JsonResponse) {
            return $parcelResult;
        }

        $binary = $parcelResult['binary'];
        $trackingReference = $parcelResult['tracking_reference'];

        $order->loadMissing('files');
        if ($order->hasSavedLabel()) {
            (new FileService)->deleteByTag($order, 'labels');
            $order->unsetRelation('files');
        }

        (new FileService)->createOrUpdate(
            inputFile: $binary,
            relatedModel: $order,
            tag: 'labels',
            saveToDatabase: true
        );

        if ($order->status !== 'shipped' && $trackingReference) {
            Log::info('[ShipStation Tracking] Shipmate label triggering ShipStation update', [
                'order_id' => $order->id,
                'marketplace_order_id' => $order->order_id,
                'tracking_number' => $trackingReference,
            ]);

            $shipStationUpdated = (new shipStationService)->shipOrder($order, $trackingReference);

            if ($shipStationUpdated) {
                Log::info('[ShipStation Tracking] Shipmate label ShipStation result', [
                    'order_id' => $order->id,
                    'marketplace_order_id' => $order->order_id,
                    'tracking_number' => $trackingReference,
                    'shipstation_updated' => true,
                ]);
            } else {
                Log::error('[ShipStation Tracking] Shipmate label ShipStation result — FAILED (see previous [ShipStation Tracking] error for reason)', [
                    'order_id' => $order->id,
                    'marketplace_order_id' => $order->order_id,
                    'tracking_number' => $trackingReference,
                    'shipstation_updated' => false,
                ]);
            }
        } elseif (! $trackingReference) {
            Log::warning('[ShipStation Tracking] Shipmate label skipped ShipStation update — no tracking reference', [
                'order_id' => $order->id,
                'marketplace_order_id' => $order->order_id,
            ]);
        } else {
            Log::info('[ShipStation Tracking] Shipmate label skipped ShipStation update — already shipped', [
                'order_id' => $order->id,
                'marketplace_order_id' => $order->order_id,
                'existing_tracking_number' => $order->tracking_number,
            ]);
        }

        $order->update([
            'status' => 'shipped',
            'tracking_number' => $trackingReference,
            'generated_by' => 'shipmate',
        ]);

        $order->refresh();
        $order->load('files');

        return response()->json([
            'success' => true,
            'fileUrl' => $order->savedLabelUrl(),
            'label_count' => (int) ($order->label_count ?? 0),
        ]);
    }

    /**
     * Additional label: new Shipmate shipment (orderId-itemId-count), print PDF only — not stored in DB.
     */
    public function generateAdditionalLabel(Order $order)
    {
        $configError = $this->shipmateConfigError();
        if ($configError !== null) {
            return $configError;
        }

        return $this->createAdditionalShipmentLabel($order);
    }

    private function createAdditionalShipmentLabel(Order $order)
    {
        $maxCount = (int) config('shipmate.max_label_print_count', 5);
        $currentCount = (int) ($order->label_count ?? 0);

        if ($currentCount >= $maxCount) {
            return response()->json([
                'success' => false,
                'error' => 'Maximum additional labels (' . $maxCount . ') reached for this order.',
                'label_count' => $currentCount,
            ]);
        }

        $nextCount = $currentCount + 1;
        $payload = $this->buildAdditionalShipmentPayload($order, $nextCount);

        $parcelResult = $this->requestShipmentLabel($payload);
        if ($parcelResult instanceof \Illuminate\Http\JsonResponse) {
            return $parcelResult;
        }

        $labelCount = $order->recordLabelPrinted();

        return response()->json([
            'success' => true,
            'fileUrl' => $this->storeTemporaryLabelPdf($parcelResult['binary'], $order, $nextCount),
            'additional_label' => true,
            'shipment_reference' => $payload['shipment_reference'],
            'tracking_number' => $parcelResult['tracking_reference'],
            'label_count' => $labelCount,
        ]);
    }

    /**
     * Temp file for preview/print only (not linked to order in DB).
     * Filename: {orderId}-{itemId}-{count}.pdf
     */
    private function storeTemporaryLabelPdf(string $binary, Order $order, int $count): string
    {
        $filename = $this->additionalLabelFileBaseName($order, $count) . '.pdf';
        $path = 'uploads/labels/temp/' . $filename;
        Storage::disk('public')->put($path, $binary);

        return asset('storage/' . $path);
    }

    private function additionalLabelFileBaseName(Order $order, int $count): string
    {
        $orderId = $this->sanitizeLabelFileSegment((string) ($order->order_id ?? 'order'));
        $itemId = $this->sanitizeLabelFileSegment((string) ($order->order_item_id ?: $order->order_id));

        return $orderId . '-' . $itemId . '-' . $count;
    }

    private function sanitizeLabelFileSegment(string $value): string
    {
        $safe = preg_replace('/[^A-Za-z0-9._-]/', '_', trim($value));

        return $safe !== '' ? $safe : 'unknown';
    }

    private function shipmateConfigError(): ?\Illuminate\Http\JsonResponse
    {
        if ($this->apiKey === '' || $this->token === '') {
            return response()->json([
                'success' => false,
                'error' => 'Shipmate API is not configured. Set SHIPMATE_API_KEY and SHIPMATE_TOKEN in your environment.',
            ]);
        }

        if ((string) config('shipmate.delivery_service_key') === '') {
            return response()->json([
                'success' => false,
                'error' => 'Shipmate delivery service is not configured. Set SHIPMATE_DELIVERY_SERVICE_KEY.',
            ]);
        }

        return null;
    }

    private function buildAdditionalShipmentPayload(Order $order, int $count): array
    {
        return $this->buildShipmentPayloadWithReference(
            $order,
            $this->resolveShipmentReference($order, $count)
        );
    }

    /**
     * @return array{binary: string, tracking_reference: ?string}|\Illuminate\Http\JsonResponse
     */
    private function requestShipmentLabel(array $payload)
    {
        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
            'X-SHIPMATE-API-KEY' => $this->apiKey,
            'X-SHIPMATE-TOKEN' => $this->token,
        ])->post("{$this->baseUrl}/shipments", $payload);

        if (! $response->successful()) {
            $errorBody = $response->json();
            $shipmentReferenceError = $errorBody['data']['shipment_reference'][0] ?? null;

            if ($response->status() === 400 && is_string($shipmentReferenceError) && str_contains($shipmentReferenceError, 'already been taken')) {
                return response()->json([
                    'success' => false,
                    'error' => 'This shipment reference is already used in Shipmate. Try again.',
                    'details' => $errorBody,
                ]);
            }

            Log::error('Shipmate API error', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Shipmate API error: ' . $response->status(),
                'details' => $errorBody ?? $response->body(),
            ]);
        }

        $body = $response->json();
        $parcel = $body['data'][0] ?? null;

        if (! is_array($parcel)) {
            return response()->json([
                'success' => false,
                'error' => 'Unexpected Shipmate response format',
                'details' => $body,
            ]);
        }

        $labelPdf = $parcel['pdf'] ?? '';
        if ($labelPdf === '') {
            return response()->json([
                'success' => false,
                'error' => 'PDF label not found in Shipmate response. Ensure format is set to PDF.',
                'details' => $parcel,
            ]);
        }

        $binary = base64_decode($labelPdf, true);
        if ($binary === false) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to decode Shipmate PDF label',
            ]);
        }

        return [
            'binary' => $binary,
            'tracking_reference' => $parcel['tracking_reference'] ?? null,
        ];
    }

    private function buildShipmentPayload(Order $order): array
    {
        return $this->buildShipmentPayloadWithReference($order, $this->resolveShipmentReference($order));
    }

    private function maxShipmentReferenceLength(): int
    {
        return (int) config('shipmate.max_shipment_reference_length', 30);
    }

    /**
     * Main: orderId-itemId. Additional: itemId-count only. Capped at 30 chars (Parcelforce).
     */
    private function resolveShipmentReference(Order $order, ?int $additionalCount = null): string
    {
        $max = $this->maxShipmentReferenceLength();

        if ($additionalCount !== null) {
            $itemId = (string) ($order->order_item_id ?: $order->order_id);
            $suffix = '-' . $additionalCount;
            $baseMax = max(1, $max - strlen($suffix));

            return $this->limitReference(substr($itemId, 0, $baseMax) . $suffix, $max);
        }

        $orderId = (string) ($order->order_id ?? '');
        $itemId = (string) ($order->order_item_id ?? '');

        return $this->compactOrderItemReference($orderId, $itemId, $max);
    }

    private function compactOrderItemReference(string $orderId, string $itemId, int $maxLen): string
    {
        if ($itemId === '') {
            return substr($orderId, 0, $maxLen);
        }

        $combined = $orderId . '-' . $itemId;
        if (strlen($combined) <= $maxLen) {
            return $combined;
        }

        $orderLen = min(strlen($orderId), max(1, (int) floor(($maxLen - 1) * 0.55)));
        $itemLen = max(1, $maxLen - 1 - $orderLen);

        return substr($orderId, 0, $orderLen) . '-' . substr($itemId, -$itemLen);
    }

    private function limitReference(string $value, ?int $max = null): string
    {
        $max ??= $this->maxShipmentReferenceLength();

        return strlen($value) <= $max ? $value : substr($value, 0, $max);
    }

    private function buildShipmentPayloadWithReference(Order $order, string $shipmentReference): array
    {
        $shipmentReference = $this->limitReference($shipmentReference);
        $parcelReference = $this->limitReference($shipmentReference . '-1');
        $addressLines = $this->splitShipmateAddress($order->address);

        $payload = [
            'shipment_reference' => $shipmentReference,
            'order_reference' => (string) ($order->order_id ?? $shipmentReference),
            'format' => 'PDF',
            'print_labels' => false,
            'to_address' => [
                'name' => $order->recipient_name ?? '',
                'line_1' => $addressLines[0] ?? '',
                'line_2' => $addressLines[1] ?? '',
                'line_3' => $addressLines[2] ?? '',
                'city' => $order->city ?? '',
                'postcode' => $order->postal_code ?? '',
                'country' => $order->country ?: 'GB',
            ],
            'parcels' => [
                [
                    'reference' => $parcelReference,
                    'weight' => (int) config('shipmate.default_weight_grams'),
                    'length' => (int) config('shipmate.default_length_cm'),
                    'width' => (int) config('shipmate.default_width_cm'),
                    'depth' => (int) config('shipmate.default_depth_cm'),
                    'value' => round((float) config('shipmate.default_parcel_value'), 2),
                    'items' => [
                        [
                            'harmonised_code'   => (string) config('shipmate.customs_harmonised_code', '87089900'),
                            'short_description' => (string) config('shipmate.customs_short_description', 'Car Mats'),
                            'full_description'  => (string) config('shipmate.customs_full_description', 'Automotive Car Mats'),
                            'item_value'        => round((float) config('shipmate.default_parcel_value'), 2),
                            'item_quantity'     => 1,
                            'item_weight'       => (int) config('shipmate.default_weight_grams'),
                            'country_of_origin' => (string) config('shipmate.customs_country_of_origin', 'GB'),
                        ],
                    ],
                ],
            ],
        ];

        $payload['delivery_service_key'] = (string) config('shipmate.delivery_service_key');

        $deliveryInstructions = trim((string) ($order->description ?? ''));
        if ($deliveryInstructions !== '') {
            $payload['delivery_instructions'] = $deliveryInstructions;
        }

        $payload['customs_declaration'] = [
            'reason_for_export' => (string) config('shipmate.customs_reason_for_export', 'SALE'),
            'incoterms'         => (string) config('shipmate.customs_incoterms', 'DAP'),
        ];

        return $payload;
    }

    private function splitShipmateAddress(?string $address): array
    {
        $normalized = trim(str_replace("\xc2\xa0", ' ', (string) $address));
        if ($normalized === '') {
            return [''];
        }

        $maxLen = 39; // Shipmate expects line_1 < 40 chars.
        $chunks = preg_split('/\s*,\s*|\s+/', $normalized, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $lines = [];
        $current = '';

        foreach ($chunks as $chunk) {
            $candidate = $current === '' ? $chunk : ($current . ' ' . $chunk);
            if (strlen($candidate) <= $maxLen) {
                $current = $candidate;
                continue;
            }

            if ($current !== '') {
                $lines[] = $current;
            }

            while (strlen($chunk) > $maxLen) {
                $lines[] = substr($chunk, 0, $maxLen);
                $chunk = substr($chunk, $maxLen);
            }
            $current = $chunk;

            if (count($lines) >= 3) {
                break;
            }
        }

        if ($current !== '' && count($lines) < 3) {
            $lines[] = $current;
        }

        $lines = array_slice($lines, 0, 3);
        if (empty($lines)) {
            $lines[] = '';
        }

        return $lines;
    }
}
