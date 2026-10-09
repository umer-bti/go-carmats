<?php

namespace App\Http\Controllers\Console\Return;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\AmazonReturn;
use App\Models\Order;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Yajra\DataTables\DataTables;
use Milon\Barcode\Facades\DNS1DFacade as DNS1D;

class ReturnsController extends Controller
{
    public function index(Request $request)
    {
        if ($request->ajax()) {
            $query = AmazonReturn::query();

            // Filter by tracking number
            if ($request->filled('tracking')) {
                $query->where('tracking', 'like', '%' . $request->input('tracking') . '%');
            }

            // Filter by order ID
            if ($request->filled('order_id')) {
                $query->where('order_id', 'like', '%' . $request->input('order_id') . '%');
            }

            // Filter by return date
            if ($request->filled('start_date')) {
                $query->whereDate('return_request_date', '>=', $request->input('start_date'));
            }
            
            if ($request->filled('end_date')) {
                $query->whereDate('return_request_date', '<=', $request->input('end_date'));
            }

            // Filter by received status
            if ($request->filled('received_status')) {
                if ($request->input('received_status') === 'received') {
                    $query->where('received', true);
                } elseif ($request->input('received_status') === 'pending') {
                    $query->where('received', false);
                }
            }

            return DataTables::of($query)
                ->addIndexColumn()
                ->editColumn('return_request_date', function ($row) {
                    return $row->return_request_date;
                })
                ->addColumn('received_badge', function ($row) {
                    if ($row->received) {
                        return '<span class="badge bg-success">Received</span>';
                    } else {
                        return '<span class="badge bg-warning">Pending</span>';
                    }
                })
                ->addColumn('action', function ($row) {
                    if (!$row->received) {
                        return '<button class="btn btn-sm btn-primary" onclick="openMarkAsReceivedModal(this)" data-id="' . $row->id . '">Mark as Received and Print Label</button>';
                    } else {
                        return '<button class="btn btn-sm btn-warning" onclick="unmarkAsReceived(this)" data-id="' . $row->id . '">Unmark as Received</button>';
                    }
                })
                ->setRowClass(function ($row) {
                    // Check if this return is linked to any order
                    return $row->relatedOrder()->exists() ? 'linked-to-order' : '';
                })
                ->rawColumns(['received_badge', 'action'])
                ->make(true);
        } else {
            return view('console.returns.index');
        }
    }

    public function markAsReceived(Request $request)
    {
        $request->validate([
            'id' => 'required|exists:amazon_returns,id',
            'notes' => 'nullable|string',
        ]);

        $return = AmazonReturn::findOrFail($request->id);

        // 1. Mark the return as received immediately
        $return->update([
            'received' => true,
            'notes' => $request->input('notes'),
        ]);

        $assignedOrder = null;

        // 2. Attempt to assign to an eligible awaiting_shipment order.
        // Wrapped in try-catch so any unexpected error during order assignment
        // will never revert or fail the received status of the return.
        try {
            $trimmedSku = trim((string) $return->sku);
            $trimmedName = trim((string) $return->item_name);
            $trimmedMat = trim((string) $return->material_type);

            $hasSku = ($trimmedSku !== '');
            $hasNameAndMat = ($trimmedName !== '' && $trimmedMat !== '');

            // Loophole guard: Only attempt matching if we have at least a valid SKU
            // or a valid (Make/Model + Material Type). Otherwise, skip to prevent
            // matching arbitrary unrelated orders.
            if ($hasSku || $hasNameAndMat) {
                DB::transaction(function () use ($return, $trimmedSku, $trimmedName, $trimmedMat, $hasSku, $hasNameAndMat, &$assignedOrder) {
                    // Lock the return row to prevent race conditions from concurrent clicks
                    $lockedReturn = AmazonReturn::lockForUpdate()->find($return->id);
                    if (! $lockedReturn || $lockedReturn->relatedOrder()->exists()) {
                        return;
                    }

                    // Find candidate order that:
                    // - Status is awaiting_shipment
                    // - Not assigned to any return or prestock yet
                    // - Not in any cutting batch yet
                    // - Matches by SKU or (Make/Model + Material)
                    // - Oldest unassigned order first (FIFO)
                    $matchingOrder = Order::query()
                        ->whereRaw("TRIM(status) = 'awaiting_shipment'")
                        ->whereNull('amazon_return_id')
                        ->whereNull('prestock_id')
                        ->whereDoesntHave('batchOrders')
                        ->where(function ($query) use ($trimmedSku, $trimmedName, $trimmedMat, $hasSku, $hasNameAndMat) {
                            if ($hasSku && $hasNameAndMat) {
                                $query->where('sku', $trimmedSku)
                                    ->orWhere(function ($q) use ($trimmedName, $trimmedMat) {
                                        $q->whereRaw('LOWER(TRIM(make_model)) = ?', [mb_strtolower($trimmedName)])
                                          ->whereRaw('LOWER(TRIM(material_type)) = ?', [mb_strtolower($trimmedMat)]);
                                    });
                            } elseif ($hasSku) {
                                $query->where('sku', $trimmedSku);
                            } elseif ($hasNameAndMat) {
                                $query->whereRaw('LOWER(TRIM(make_model)) = ?', [mb_strtolower($trimmedName)])
                                      ->whereRaw('LOWER(TRIM(material_type)) = ?', [mb_strtolower($trimmedMat)]);
                            }
                        })
                        ->orderBy('id', 'asc')
                        ->lockForUpdate()
                        ->first();

                    if ($matchingOrder) {
                        $matchingOrder->update([
                            'amazon_return_id' => $return->id,
                        ]);
                        $assignedOrder = $matchingOrder;
                    }
                });
            }
        } catch (\Throwable $e) {
            Log::error('Error assigning order on return markAsReceived: ' . $e->getMessage(), [
                'return_id' => $return->id,
                'error' => $e->getMessage(),
            ]);
        }

        $message = $assignedOrder
            ? "Return marked as received and assigned to Order #{$assignedOrder->order_number}."
            : 'Return marked as received successfully.';

        return response()->json([
            'success' => true,
            'message' => $message,
            'assigned_order_id' => $assignedOrder?->id,
            'assigned_order_number' => $assignedOrder?->order_number,
            'return_data' => [
                'item_name' => $return->item_name,
                'tracking' => $return->tracking,
                'notes' => $return->notes,
                'edging' => $return->edging,
                'material_type' => $return->material_type,
            ]
        ]);
    }

    public function unmarkAsReceived(Request $request)
    {
        $request->validate([
            'id' => 'required|exists:amazon_returns,id',
        ]);

        $return = AmazonReturn::findOrFail($request->id);
        
        DB::transaction(function () use ($return) {
            // Unlink any unfulfilled order attached to this return (preserving historical shipped records)
            Order::where('amazon_return_id', $return->id)
                ->where('status', '!=', 'shipped')
                ->update([
                    'amazon_return_id' => null,
                ]);

            $return->update([
                'received' => false,
                'notes' => null,
            ]);
        });

        return response()->json([
            'success' => true,
            'message' => 'Return unmarked as received successfully.',
        ]);
    }

    public function generateLabelBarcode(Request $request)
    {
        $request->validate([
            'tracking' => 'required|string',
        ]);

        try {
            $barcodePng = DNS1D::getBarcodePNG($request->tracking, 'C128', 2, 60);
            
            return response()->json([
                'success' => true,
                'barcode' => $barcodePng,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate barcode: ' . $e->getMessage(),
            ], 500);
        }
    }
}

