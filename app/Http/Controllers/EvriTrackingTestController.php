<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Services\EvriVerificationService;
use Illuminate\Http\JsonResponse;

class EvriTrackingTestController extends Controller
{
    public function show(string $barcode, EvriVerificationService $verificationService): JsonResponse
    {
        $barcode = trim(urldecode($barcode));
        $variants = EvriVerificationService::barcodeVariants($barcode);

        $order = Order::query()
            ->with(['files', 'evriShipmentVerification'])
            ->where(function ($query) use ($variants) {
                $query->whereIn('tracking_number', $variants);
            })
            ->first();

        $system = $order
            ? [
                'found' => true,
                'id' => $order->id,
                'order_id' => $order->order_id,
                'order_item_id' => $order->order_item_id,
                'generated_by' => $order->generated_by,
                ...$verificationService->snapshotSystemState($order),
                'verification' => $order->evriShipmentVerification ? [
                    'verification_result' => $order->evriShipmentVerification->verification_result,
                    'evri_label_exists' => $order->evriShipmentVerification->evri_label_exists,
                    'evri_status' => $order->evriShipmentVerification->evri_status,
                    'evri_verified_at' => $order->evriShipmentVerification->evri_verified_at?->toIso8601String(),
                    'evri_error' => $order->evriShipmentVerification->evri_error,
                ] : null,
            ]
            : [
                'found' => false,
                'message' => 'No order with this tracking number in the system.',
                'barcode_variants_checked' => $variants,
            ];

        $evriLookup = $verificationService->lookupEvriTrackingWithDebug($barcode);

        $notes = [];
        if (! $order) {
            $notes[] = 'No order found. Print this label via Shipping Labels in the app so tracking_number is saved — or search by Reference 1 (order_item_id), e.g. /evri-tracking/575793201';
        }
        if ($order && $order->generated_by !== 'evri') {
            $notes[] = 'This order was shipped via ' . $order->generated_by . ', not Evri.';
        }
        if ($order && $order->generated_by === 'evri' && ! $order->evriShipmentVerification) {
            $notes[] = 'No verification row yet — run cron or Verify on Reports page.';
        }

        return response()->json([
            'barcode' => $barcode,
            'error_summary' => $evriLookup['error_summary'] ?? $evriLookup['result']['error'] ?? null,
            'system' => $system,
            'evri' => $evriLookup['result'],
            'evri_barcode_used' => $evriLookup['barcode_used'],
            'debug' => $evriLookup['debug'],
            'notes' => $notes,
        ]);
    }
}
