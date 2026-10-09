<?php

namespace App\Http\Controllers\Console\Shipmate;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Stitcher;
use App\Services\shipmateService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ShipmateController extends Controller
{
    public function index()
    {
        $stitchers = Stitcher::query()->orderBy('name')->get(['id', 'name']);

        return view('console.shipmate.index', compact('stitchers'));
    }

    public function fetchOrder($id)
    {
        $orderResponse = Order::with('files')->where('order_id', $id)->get();

        if ($orderResponse) {
            return response()->json(['success' => true, 'orders' => $orderResponse]);
        }

        return response()->json(['success' => false, 'orders' => []]);
    }

    public function generateAndPrintOrderLabel($id, Request $request, shipmateService $shipmateService)
    {
        $validated = $request->validate([
            'itemId' => 'required',
            'stitcher_id' => 'required|exists:stitchers,id',
            'force_shipmate' => 'sometimes|boolean',
        ]);

        $forceShipmate = $request->boolean('force_shipmate');

        $stitcherId = (int) $validated['stitcher_id'];
        $order = Order::with('files')
            ->where('order_id', $id)
            ->where('order_item_id', $request->itemId)
            ->first();

        if (! $order) {
            return response()->json(['success' => false, 'error' => 'Order not found']);
        }

        $assignmentApplied = $order->stitcher_id === null;

        if ($assignmentApplied) {
            $order->update([
                'stitcher_id' => $stitcherId,
                'scan_time' => now(),
            ]);
        }

        $order->refresh();
        $order->load('files');

        $assignmentPayload = [
            'assignment_applied' => $assignmentApplied,
            'already_assigned' => ! $assignmentApplied,
        ];
        if (! $assignmentApplied) {
            $assignmentPayload['message'] = 'This order is already assigned to a stitcher.';
        }

        if (! $forceShipmate) {
            if ($order->hasSavedLabel()) {
                return response()->json(array_merge([
                    'success' => true,
                    'fileUrl' => $order->savedLabelUrl(),
                    'reused_existing_label' => true,
                    'generated_by' => $order->generated_by,
                    'label_count' => (int) ($order->label_count ?? 0),
                ], $assignmentPayload));
            }

            if ($order->generated_by) {
                return response()->json(array_merge([
                    'success' => false,
                    'error' => 'Label already generated via ' . $order->generated_by . ', but saved PDF was not found.',
                ], $assignmentPayload));
            }
        }

        if (! Gate::allows('create labels')) {
            return response()->json(array_merge([
                'success' => false,
                'error' => 'You do not have permission to create new labels.',
            ], $assignmentPayload), 403);
        }

        $response = $shipmateService->generateLabel($order);
        $data = $response->getData(true);

        if (is_array($data) && ($data['success'] ?? false)) {
            return response()->json(array_merge($data, $assignmentPayload));
        }

        return $response;
    }

    public function generateAdditionalLabel($id, Request $request, shipmateService $shipmateService)
    {
        $request->validate([
            'itemId' => 'required',
        ]);

        $order = Order::where('order_id', $id)
            ->where('order_item_id', $request->itemId)
            ->first();

        if (! $order) {
            return response()->json(['success' => false, 'error' => 'Order not found']);
        }

        if (! Gate::allows('create labels')) {
            return response()->json([
                'success' => false,
                'error' => 'You do not have permission to create new labels.',
            ], 403);
        }

        return $shipmateService->generateAdditionalLabel($order);
    }
}
