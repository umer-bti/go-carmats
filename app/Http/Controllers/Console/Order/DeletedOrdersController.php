<?php

namespace App\Http\Controllers\Console\Order;

use App\Http\Controllers\Controller;
use App\Http\Concerns\VerifiesAccessPassword;
use Illuminate\Http\Request;
use App\Models\Order;
use Yajra\DataTables\DataTables;

class DeletedOrdersController extends Controller
{
    use VerifiesAccessPassword;
    public function index(Request $request)
    {
        if ($request->ajax()) {
            $query = Order::onlyTrashed();

            // Filter by material type
            if ($request->filled('material_type')) {
                $query->where('material_type', $request->input('material_type'));
            }

            // Filter by date range
            if ($request->filled('start_date')) {
                $query->whereDate('deleted_at', '>=', $request->input('start_date'));
            }
            
            if ($request->filled('end_date')) {
                $query->whereDate('deleted_at', '<=', $request->input('end_date'));
            }

            return DataTables::of($query)
                ->addIndexColumn()
                ->addColumn('deleted_at_formatted', function ($row) {
                    return $row->deleted_at ? $row->deleted_at->format('Y-m-d H:i:s') : '-';
                })
                ->addColumn('action', function ($row) {
                    $buttons = '';
                    $buttons .= '<button class="btn btn-sm btn-success me-1" onclick="restoreOrder(this)" data-id="' . $row->id . '"><i class="fas fa-undo me-1"></i>Restore</button>';
                    $buttons .= '<button class="btn btn-sm btn-danger" onclick="permanentDeleteOrder(this)" data-id="' . $row->id . '"><i class="fas fa-trash-alt me-1"></i>Delete Permanently</button>';
                    return $buttons;
                })
                ->rawColumns(['action'])
                ->make(true);
        } else {
            return view('console.orders.deleted');
        }
    }

    public function restore(Request $request)
    {
        $request->validate([
            'id' => 'required|exists:orders,id',
        ]);

        $order = Order::onlyTrashed()->findOrFail($request->id);
        $order->restore();

        return response()->json([
            'success' => true,
            'message' => 'Order restored successfully.',
        ]);
    }

    public function forceDelete(Request $request)
    {
        $request->validate([
            'id' => 'required|exists:orders,id',
            'password' => 'required|string',
        ]);

        if ($denied = $this->denyUnlessAccessPassword($request)) {
            return $denied;
        }

        $order = Order::onlyTrashed()->findOrFail($request->id);
        $order->forceDelete();

        return response()->json([
            'success' => true,
            'message' => 'Order permanently deleted.',
        ]);
    }
}

