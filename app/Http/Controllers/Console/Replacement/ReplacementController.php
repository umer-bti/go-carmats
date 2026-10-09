<?php

namespace App\Http\Controllers\Console\Replacement;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\ProductSetting;
use App\Models\Replacement;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Yajra\DataTables\Facades\DataTables;

class ReplacementController extends Controller
{
    public function index(Request $request)
    {
        if ($request->ajax()) {
            // 'pending' tab: not printed, 'printed' tab: printed
            $printed = $request->input('is_printed');

            $query = Replacement::select('replacements.*');

            if ($printed === '1') {
                $query->where('is_printed', true);
            } elseif ($printed === '0') {
                $query->where('is_printed', false);
            }
            // No filter means return all (fallback safety)

            return DataTables::of($query)
                ->addColumn('checkbox', function ($row) {
                    return '<input type="checkbox" class="form-check-input row-checkbox" value="' . $row->id . '">';
                })
                ->addColumn('customer', function ($row) {
                    return $row->customer;
                })
                ->addColumn('created_date', function ($row) {
                    return $row->created_at->format('d-m-Y H:i:s');
                })
                ->addColumn('printed_date', function ($row) {
                    return $row->printed_at ? $row->printed_at->format('d-m-Y H:i:s') : '-';
                })
                ->addColumn('selected_items', function ($row) {
                    $items = is_string($row->selected_items) ? json_decode($row->selected_items, true) : $row->selected_items;
                    if (! is_array($items)) {
                        return '-';
                    }
                    $ids = array_filter(array_column($items, 'order_item_id'));
                    return implode(', ', $ids) ?: '-';
                })
                ->addColumn('material_type', function ($row) {
                    $items = is_string($row->selected_items) ? json_decode($row->selected_items, true) : $row->selected_items;
                    if (! is_array($items)) return '-';
                    $values = array_unique(array_filter(array_column($items, 'material_type')));
                    return implode('<br>', $values) ?: '-';
                })
                ->addColumn('edging', function ($row) {
                    $items = is_string($row->selected_items) ? json_decode($row->selected_items, true) : $row->selected_items;
                    if (! is_array($items)) return '-';
                    $values = array_unique(array_filter(array_column($items, 'edging')));
                    return implode('<br>', $values) ?: '-';
                })
                ->addColumn('status', function ($row) {
                    $items = is_string($row->selected_items) ? json_decode($row->selected_items, true) : $row->selected_items;
                    if (! is_array($items)) return '-';
                    $values = array_unique(array_filter(array_column($items, 'status')));
                    return implode('<br>', $values) ?: '-';
                })
                ->addColumn('source', function ($row) {
                    $items = is_string($row->selected_items) ? json_decode($row->selected_items, true) : $row->selected_items;
                    if (! is_array($items)) return '-';
                    $values = array_unique(array_filter(array_column($items, 'source')));
                    return implode('<br>', $values) ?: '-';
                })
                ->addColumn('action', function ($row) {
                    return '<button type="button" class="btn btn-sm btn-primary" onclick="openReplacementPrintModal([' . $row->id . '])">
                        <i class="fas fa-print me-1"></i>Print Label
                    </button>';
                })
                ->addColumn('printed_action', function ($row) {
                    return '<button type="button" class="btn btn-sm btn-primary" onclick="openReplacementPrintModal([' . $row->id . '])">
                        <i class="fas fa-print me-1"></i>Reprint Label
                    </button>';
                })
                ->rawColumns(['checkbox', 'selected_items', 'material_type', 'edging', 'status', 'source', 'action', 'printed_action'])
                ->make(true);
        }

        return view('console.replacements.index');
    }

    public function search(Request $request)
    {
        $orderId = $request->get('order_id');
        if (! $orderId) {
            return response()->json(['success' => false, 'message' => 'Order ID is required.']);
        }

        $orders = Order::where('order_id', $orderId)->get();

        if ($orders->isEmpty()) {
            return response()->json(['success' => false, 'message' => 'Order not found.']);
        }

        $ordersData = $orders->map(function ($o) {
            return [
                'id'            => $o->id,
                'order_id'      => $o->order_id,
                'sku'           => $o->sku,
                'product'       => $o->additional_data['product'] ?? ($o->make_model ?? 'Unknown Product'),
                'designType'    => $o->sku,
                'quantity'      => $o->quantity,
                'order_item_id' => $o->order_item_id,
                'material_type' => $o->material_type,
                'edging'        => $o->edging,
                'status'        => $o->status,
            ];
        });

        return response()->json([
            'success' => true,
            'orders'  => $ordersData,
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'order_id'         => 'required|string',
            'reason'           => 'required|string',
            'selected_items'   => 'required|array|min:1',
            'selected_items.*' => 'integer',
        ]);

        $orders = Order::whereIn('id', $request->selected_items)->get()->keyBy('id');

        if ($orders->isEmpty()) {
            return response()->json(['success' => false, 'message' => 'Selected items are invalid.']);
        }

        foreach ($request->selected_items as $orderId) {
            $ord = $orders->get($orderId);
            if (! $ord) continue;

            $itemDetail = [
                'id'            => $ord->id,
                'sku'           => $ord->sku,
                'make_model'    => $ord->make_model,
                'product'       => $ord->additional_data['product'] ?? ($ord->make_model ?? 'Unknown Product'),
                'quantity'      => $ord->quantity,
                'order_item_id' => $ord->order_item_id,
                'material_type' => $ord->material_type,
                'edging'        => $ord->edging,
                'status'        => $ord->status,
                'source'        => $ord->source,
            ];

            Replacement::create([
                'order_id'       => $request->order_id,
                'customer'       => $ord->recipient_name ?? null,
                'selected_items' => json_encode([$itemDetail]),
                'reason'         => $request->reason,
                'description'    => $request->description,
                'is_printed'     => false,
            ]);
        }

        $count = count($request->selected_items);
        $message = $count === 1
            ? 'Replacement created successfully.'
            : "{$count} replacements created successfully.";

        return response()->json(['success' => true, 'message' => $message]);
    }

    public function print(Request $request)
    {
        $ids = $this->resolveReplacementIds($request);

        if (empty($ids)) {
            return response()->json([
                'success' => false,
                'message' => 'No replacement selected.',
            ], 404);
        }

        $replacements = Replacement::whereIn('id', $ids)->get()
            ->sortBy(fn ($replacement) => array_search($replacement->id, $ids))
            ->values();

        if ($replacements->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Replacement not found.',
            ], 404);
        }

        $orders = $this->loadOrdersForReplacements($replacements);

        if ($orders->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'No label items found for the selected replacement(s).',
            ], 404);
        }

        // Build a map of order DB-id => replacement reason so the partial can display it
        $reasonMap = [];
        foreach ($replacements as $replacement) {
            $items = is_string($replacement->selected_items)
                ? json_decode($replacement->selected_items, true)
                : $replacement->selected_items;

            if (is_array($items)) {
                foreach ($items as $item) {
                    if (! empty($item['id'])) {
                        $reasonMap[(int) $item['id']] = $replacement->reason;
                    }
                }
            }
        }

        return response()->json([
            'success' => true,
            'html'    => view('console.replacements.partials.print-label-cards', compact('orders', 'reasonMap'))->render(),
        ]);
    }

    /**
     * Mark replacements as printed (called after user confirms printing).
     */
    public function markAsPrinted(Request $request)
    {
        $ids = $this->resolveReplacementIds($request);

        if (empty($ids)) {
            return response()->json([
                'success' => false,
                'message' => 'No replacement selected.',
            ], 422);
        }

        Replacement::whereIn('id', $ids)->update([
            'is_printed' => true,
            'printed_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Replacement(s) marked as printed.',
        ]);
    }

    /**
     * @return array<int, int>
     */
    private function resolveReplacementIds(Request $request): array
    {
        $rawIds = $request->input('ids', $request->input('replacement_id'));

        if (is_string($rawIds)) {
            $rawIds = explode(',', $rawIds);
        }

        if (! is_array($rawIds)) {
            return [];
        }

        return array_values(array_unique(array_filter(array_map('intval', $rawIds))));
    }

    /**
     * Load Order models for selected replacement line items, matching Batch Management process().
     *
     * @param  Collection<int, Replacement>  $replacements
     * @return Collection<int, Order>
     */
    private function loadOrdersForReplacements(Collection $replacements): Collection
    {
        $orderIds = [];

        foreach ($replacements as $replacement) {
            $items = is_string($replacement->selected_items)
                ? json_decode($replacement->selected_items, true)
                : $replacement->selected_items;

            if (! is_array($items)) {
                continue;
            }

            foreach ($items as $item) {
                if (! empty($item['id'])) {
                    $orderIds[] = (int) $item['id'];
                }
            }
        }

        $orderIds = array_values(array_unique($orderIds));

        if (empty($orderIds)) {
            return collect();
        }

        $orders = Order::whereIn('id', $orderIds)->get()
            ->sortBy(fn ($order) => array_search($order->id, $orderIds))
            ->values();

        foreach ($orders as $order) {
            $productSetting = ProductSetting::whereJsonContains('skus', $order->sku)->first();
            $order->product_setting = $productSetting ?: null;
        }

        return $orders;
    }

    public function destroy($id)
    {
        $replacement = Replacement::findOrFail($id);
        $replacement->delete();

        return response()->json([
            'success' => true,
            'message' => 'Replacement deleted successfully.',
        ]);
    }
}
