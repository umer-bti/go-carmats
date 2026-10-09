<?php

namespace App\Services;

use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Amazon Prime labels via SP-API Shipping v2 (alternative to Veeqo).
 * Not wired in yet — swap provider when ready.
 */
class AmazonSpApiShippingService
{
    private ?string $accessToken = null;

    public function generateLabel(Order $order): JsonResponse
    {
        $amazonOrderId = trim((string) $order->order_id);
        if ($amazonOrderId === '') {
            return response()->json(['success' => false, 'error' => 'Missing Amazon order ID.']);
        }

        try {
            $packageRef = (string) ($order->order_item_id ?: $order->id);
            $shipFrom = [
                'name' => 'Erde Ventus Ltd',
                'addressLine1' => 'Unit 5 Aneal Business Centre',
                'addressLine2' => 'Cross Green Approach',
                'city' => 'Leeds',
                'postalCode' => 'LS9 0SG',
                'countryCode' => 'GB',
            ];

            // 1) Get rates
            $ratesResponse = $this->post('/shipping/v2/shipments/rates', [
                'shipFrom' => $shipFrom,
                'packages' => [[
                    'dimensions' => ['length' => 80, 'width' => 20, 'height' => 20, 'unit' => 'CENTIMETER'],
                    'weight' => ['unit' => 'GRAM', 'value' => 1000],
                    'insuredValue' => ['value' => 10, 'unit' => 'GBP'],
                    'packageClientReferenceId' => $packageRef,
                    'items' => [[
                        'itemIdentifier' => (string) ($order->order_item_id ?: $order->id),
                        'description' => 'Car Mats',
                        'quantity' => max(1, (int) ($order->quantity ?: 1)),
                        'weight' => ['unit' => 'GRAM', 'value' => 1000],
                        'itemValue' => ['value' => 10, 'unit' => 'GBP'],
                    ]],
                ]],
                'channelDetails' => [
                    'channelType' => 'AMAZON',
                    'amazonOrderDetails' => ['orderId' => $amazonOrderId],
                ],
            ]);

            if (! $ratesResponse->successful()) {
                return response()->json([
                    'success' => false,
                    'error' => 'Amazon getRates failed.',
                    'details' => $ratesResponse->body(),
                ]);
            }

            $ratesPayload = $ratesResponse->json('payload');
            $rates = $ratesPayload['rates'] ?? [];
            if ($rates === []) {
                return response()->json([
                    'success' => false,
                    'error' => 'No Amazon shipping rates found.',
                    'details' => $ratesPayload['ineligibleRates'] ?? null,
                ]);
            }

            $rate = $this->pickRate($rates);
            $requestToken = $ratesPayload['requestToken'] ?? '';

            // 2) Buy label (PDF)
            $purchaseResponse = $this->post('/shipping/v2/shipments', [
                'requestToken' => $requestToken,
                'rateId' => $rate['rateId'],
                'requestedDocumentSpecification' => [
                    'format' => 'PDF',
                    'size' => ['width' => 4, 'length' => 6, 'unit' => 'INCH'],
                    'needFileJoining' => false,
                    'requestedDocumentTypes' => ['LABEL'],
                ],
            ]);

            if (! $purchaseResponse->successful()) {
                return response()->json([
                    'success' => false,
                    'error' => 'Amazon purchaseShipment failed.',
                    'details' => $purchaseResponse->body(),
                ]);
            }

            $purchasePayload = $purchaseResponse->json('payload') ?? [];
            $labelBinary = $this->labelFromPayload($purchasePayload);
            $trackingNumber = $purchasePayload['packageDocumentDetails'][0]['trackingId'] ?? null;

            if (! $labelBinary) {
                return response()->json(['success' => false, 'error' => 'No label returned from Amazon.']);
            }

            // 3) Save — same as Veeqo
            $relatedOrders = Order::where('order_id', $amazonOrderId)->get();
            foreach ($relatedOrders as $orderRow) {
                (new FileService)->createOrUpdate(
                    inputFile: $labelBinary,
                    relatedModel: $orderRow,
                    tag: 'labels',
                    saveToDatabase: true
                );
                $orderRow->update([
                    'status' => 'shipped',
                    'tracking_number' => $trackingNumber,
                    'generated_by' => 'amazon_sp_api',
                ]);
            }

            $firstOrder = $relatedOrders->first();
            $firstOrder?->refresh();
            $filePath = optional($firstOrder?->files)->path;

            return response()->json([
                'success' => true,
                'fileUrl' => $filePath ? asset('storage/'.$filePath) : null,
            ]);
        } catch (\Throwable $e) {
            Log::error('Amazon SP-API label error', ['order_id' => $amazonOrderId, 'message' => $e->getMessage()]);

            return response()->json(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    private function getAccessToken(): string
    {
        if ($this->accessToken) {
            return $this->accessToken;
        }

        $response = Http::asForm()->post('https://api.amazon.com/auth/o2/token', [
            'grant_type' => 'refresh_token',
            'client_id' => config('amazon.client_id'),
            'client_secret' => config('amazon.client_secret'),
            'refresh_token' => config('amazon.refresh_token'),
        ]);

        if ($response->failed()) {
            throw new \RuntimeException('Amazon auth failed: '.$response->body());
        }

        return $this->accessToken = (string) $response->json('access_token');
    }

    private function post(string $path, array $body)
    {
        $base = rtrim(config('amazon_shipping.endpoint'), '/');

        return Http::withHeaders([
            'x-amz-access-token' => $this->getAccessToken(),
            'x-amzn-shipping-business-id' => config('amazon_shipping.shipping_business_id'),
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ])->post($base.$path, $body);
    }

    private function pickRate(array $rates): array
    {
        foreach ($rates as $rate) {
            $name = strtolower($rate['serviceName'] ?? '');
            if (str_contains($name, 'prime') || str_contains($name, 'next day')) {
                return $rate;
            }
        }

        return $rates[0];
    }

    private function labelFromPayload(array $payload): ?string
    {
        $documents = $payload['packageDocumentDetails'][0]['packageDocuments'] ?? [];

        foreach ($documents as $doc) {
            if (($doc['type'] ?? '') === 'LABEL' && ! empty($doc['contents'])) {
                $pdf = base64_decode($doc['contents'], true);

                return $pdf !== false ? $pdf : null;
            }
        }

        return null;
    }
}
