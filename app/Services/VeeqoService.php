<?php

namespace App\Services;

use App\Models\Order;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class VeeqoService
{
    protected $apiKey;
    protected $baseUrl;

    public function __construct()
    {
        $this->apiKey = config('veeqo.api_key');
        $this->baseUrl = config('veeqo.base_url');
    }

    /**
     * SEARCH ORDER
     */
    public function searchOrder($query)
    {
        try {
            $response = Http::withHeaders([
                    'x-api-key' => $this->apiKey,
                    'Accept' => 'application/json',
                ])
                ->get($this->baseUrl . '/orders', [
                    'query' => $query
                ]);

            return $response->throw()->json();

        } catch (\Exception $e) {
            Log::error('Veeqo searchOrder error', [
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * UPDATE ALLOCATION PACKAGE
     */
    public function updateAllocationPackage($allocationId)
    {
        try {
           $payload = [
                'allocation_package' => [
                    'weight' => 1000,
                    'weight_unit' => 'g',
                    // 'height' => 1,
                    // 'width'  => 1,
                    // 'depth'  => 1,
                    'package_provider' => 'CUSTOM',
                    'package_selection_source' => 'ONE_OFF',
                ],
                'save_for_similar_shipments' => false,
            ];

            $response = Http::withHeaders([
                    'x-api-key' => $this->apiKey,
                    'Accept' => 'application/json',
                ])
                ->put(
                    $this->baseUrl . "/allocations/{$allocationId}/allocation_package",
                    $payload
                );

            return $response->throw()->json();

        } catch (\Exception $e) {
            Log::error('Veeqo updateAllocationPackage error', [
                'allocation_id' => $allocationId,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * GET SHIPPING RATES
     */
    public function getShippingRates($allocationId)
    {
        $response = Http::withHeaders([
                    'x-api-key' => $this->apiKey,
                    'Accept' => 'application/json',
                ])
                ->get(
                    "https://api.veeqo.com/shipping/rates/{$allocationId}",
                    [
                        'from_allocation_package' => true,
                    ]
                );
        try {
            
        return $response->throw()->json();

        } catch (\Exception $e) {
            Log::error('Veeqo getShippingRates error', [
                'allocation_id' => $allocationId,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    public function pickBestRate(array $rates)
    {
        $available = $rates['available'] ?? [];

        $best = null;

        foreach ($available as $rate) {
            $title = strtolower($rate['title'] ?? '');


            if (str_contains($title, 'next day')) {
                return $rate;
            }

            if (str_contains($title, 'two day') && !$best) {
                $best = $rate;
            }

            if (str_contains($title, 'standard') && !$best) {
                $best = $rate;
            }

            if (!$best) {
                $best = $rate;
            }
        }

        return $best;
    }

    public function createShipment($allocationId, array $rate)
    {

        try {
          
            $payload = [
                'carrier' => $rate['carrier'] ?? null,
                'shipment' => [
                    'allocation_id' => $allocationId ?? null,
                    'carrier_id' => $rate['service_carrier'] ?? null,
                    'remote_shipment_id' => $rate['remote_shipment_id'] ?? null,
                    'service_type' => $rate['name'],
                    'notify_customer' => false,
                    'sub_carrier_id' => $rate['sub_carrier_id'] ?? null,
                    'service_carrier' => $rate['service_carrier'] ?? null,
                    'total_net_charge' => $rate['total_net_charge'] ?? null,
                    'base_rate'  => $rate['base_rate'] ?? null,
                    'value_added_service__VAS_GROUP_ID_CONFIRMATION' => 'DELIVERY_CONFIRMATION',
                ],
            ];

            $response = Http::withHeaders([
                    'x-api-key' => $this->apiKey,
                    'Accept' => 'application/json',
                ])
                ->post(
                    $this->baseUrl . "/shipping/shipments",
                    $payload
                );

            return $response->throw()->json();

        } catch (\Exception $e) {
            Log::error('Veeqo createShipment error', [
                'allocation_id' => $allocationId,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    public function getShippingLabelPdf($shipmentId)
    {
        try {
            $response = Http::withHeaders([
                    'x-api-key' => $this->apiKey,
                    'Accept' => 'application/pdf',
                ])
                ->get($this->baseUrl . '/shipping/labels', [
                    'format' => 'pdf',
                    'shipment_ids' => $shipmentId,
                ]);

            if (!$response->successful()) {
                Log::error('Veeqo label fetch failed', [
                    'shipment_id' => $shipmentId,
                    'body' => $response->body()
                ]);

                return null;
            }

            return $response->body(); // ✅ raw PDF binary

        } catch (\Exception $e) {
            Log::error('Veeqo label exception', [
                'shipment_id' => $shipmentId,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    public function generateLabel(Order $order)
    {
     
        $orderId = $order->order_id;
        $orders = Order::where('order_id', $orderId)->get();

        $veeqoResponse = $this->searchOrder($orderId);
        if (empty($veeqoResponse) || count($veeqoResponse) === 0) {
            return response()->json([
                'success' => false,
                'error' => 'Order not found in Veeqo',
            ]);
        }

        $veeqoOrder = $veeqoResponse[0];
        $allocationId = $veeqoOrder['allocations'][0]['id'] ?? null;


        Log::info('Allocation ID: '.$allocationId, [
            'veeqo_order' => $veeqoOrder,
        ]);

        if (!$allocationId) {
            return response()->json([
                'success' => false,
                'error' => 'Allocation not found in Veeqo order.',
            ]);
        }

        $package = $veeqoOrder['allocations'][0]['allocation_package'] ?? [];
        $weight = (int) ($package['weight'] ?? 0);
        if ($weight === 0) {
            $this->updateAllocationPackage($allocationId);
        }

        $rates = $this->getShippingRates($allocationId);
        $available = $rates['available'] ?? [];
        if (empty($available)) {
            return response()->json([
                'success' => false,
                'error' => 'No available shipping rates for this order',
            ]);
        }

        $bestRate = $this->pickBestRate($rates);
        if (!$bestRate) {
            return response()->json([
                'success' => false,
                'error' => 'No shipping rate found',
            ]);
        }

        $shipment = $this->createShipment($allocationId, $bestRate);
        if (!is_array($shipment) || empty($shipment['id'])) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to create Veeqo shipment.',
            ]);
        }

        $labelBinary = $this->getShippingLabelPdf($shipment['id']);
        if (empty($labelBinary)) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to fetch shipping label from Veeqo.',
            ]);
        }

        $trackingNumber = $shipment['tracking_number']['tracking_number'] ?? null;
        foreach ($orders as $orderRow) {
            (new FileService)->createOrUpdate(
                inputFile: $labelBinary,
                relatedModel: $orderRow,
                tag: 'labels',
                saveToDatabase: true
            );
            $orderRow->update([
                'status' => 'shipped',
                'tracking_number' => $trackingNumber,
                'generated_by' => 'veeqo',
            ]);
        }

        // FileService::createOrUpdate may return different value types on update/create.
        // Read the persisted file path from DB to avoid type-specific assumptions.
        $firstOrder = $orders->first();
        $filePath = null;
        if ($firstOrder) {
            $firstOrder->refresh();
            $filePath = optional($firstOrder->files)->path;
        }

        return response()->json([
            'success' => true,
            'fileUrl' => $filePath ? asset('storage/'.$filePath) : null,
        ]);
    }
}