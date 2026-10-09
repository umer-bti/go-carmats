<?php

namespace App\Http\Controllers\Console\Return;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\AmazonReturn;
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
        
        $return->update([
            'received' => true,
            'notes' => $request->input('notes'),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Return marked as received successfully.',
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
        
        $return->update([
            'received' => false,
            'notes' => null,
        ]);

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

