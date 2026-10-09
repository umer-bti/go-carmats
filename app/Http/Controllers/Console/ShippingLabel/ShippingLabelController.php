<?php

namespace App\Http\Controllers\Console\ShippingLabel;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Stitcher;
use App\Services\ShippingLabelService;
use App\Services\VeeqoService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ShippingLabelController extends Controller
{


    public function index()
    {
        $stitchers = Stitcher::query()->orderBy('name')->get(['id', 'name']);

        return view('console.shipping-labels.index', compact('stitchers'));
    }

    public function fetchOrder($id)
    {
        $orderResponse = Order::with('files')->where('order_id', $id)->get();

        if ($orderResponse) {
            return response()->json(['success' => true, 'orders' => $orderResponse]);
        } else {
            return response()->json(['success' => false, 'orders' => []]);
        }
    }

    public function generateAndPrintOrderLabel($id, Request $request, ShippingLabelService $shippingLabelService, VeeqoService $veeqoService)
    {

        $validated = $request->validate([
            'itemId' => 'required',
            'stitcher_id' => 'required|exists:stitchers,id',
        ]);

        $stitcherId = (int) $validated['stitcher_id'];
        $hasPrime = (bool) $request->is_prime;
        $order = Order::with('files')
            ->where('order_id', $id)
            ->where('order_item_id', $request->itemId)
            ->first();

        if (!$order) {

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

        if (! Gate::allows('create labels')) {
            return response()->json(array_merge([
                'success' => false,
                'error' => 'You do not have permission to create new labels.',
            ], $assignmentPayload), 403);
        }

        $provider = $hasPrime ? $veeqoService : $shippingLabelService;

        $response = $provider->generateLabel($order);

        $data = $response->getData(true);

        if (is_array($data) && ($data['success'] ?? false)) {
            return response()->json(array_merge($data, $assignmentPayload));
        }

        return $response;
    }

    public function generateAdditionalLabel($id, Request $request, ShippingLabelService $shippingLabelService)
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

        if ($order->source === 'Amazon_Prime') {
            return response()->json([
                'success' => false,
                'error' => 'Additional Evri labels are not available for Amazon Prime orders.',
            ]);
        }

        if (! Gate::allows('create labels')) {
            return response()->json([
                'success' => false,
                'error' => 'You do not have permission to create new labels.',
            ], 403);
        }

        return $shippingLabelService->generateAdditionalLabel($order);
    }
}
