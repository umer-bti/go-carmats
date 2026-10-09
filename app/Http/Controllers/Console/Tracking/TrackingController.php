<?php

namespace App\Http\Controllers\Console\Tracking;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Models\Order;
use Yajra\DataTables\DataTables;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\TrackingExport;
use App\Services\shipStationService;

class TrackingController extends Controller
{
    public function index(Request $request)
    {
        if ($request->ajax()) {
            $query = Order::query()->whereNotNull('tracking_number');

            if ($request->filled('start_date')) {
                $query->whereDate('date', '>=', $request->input('start_date'));
            }

            if ($request->filled('end_date')) {
                $query->whereDate('date', '<=', $request->input('end_date'));
            }

            if ($request->filled('order_id')) {
                $query->where('order_id', 'like', '%' . $request->input('order_id') . '%');
            }

            if ($request->filled('tracking_number')) {
                $query->where('tracking_number', 'like', '%' . $request->input('tracking_number') . '%');
            }

            return DataTables::of($query)
                ->addIndexColumn()
                ->addColumn('action', function ($row) {
                    return '';
                })
                ->make(true);
        }

        return view('console.tracking.index');
    }

    public function getAwaitingShipmentOrders(Request $request)
    {
        $query = Order::query()
            ->where('status', 'awaiting_shipment')
            ->whereNull('tracking_number');

        // Search term for Select2
        if ($request->filled('q')) {
            $searchTerm = $request->input('q');
            $query->where(function($q) use ($searchTerm) {
                $q->where('order_id', 'like', '%' . $searchTerm . '%')
                  ->orWhere('make_model', 'like', '%' . $searchTerm . '%')
                  ->orWhere('recipient_name', 'like', '%' . $searchTerm . '%');
            });
        }

        $orders = $query->limit(50)->get();

        return response()->json([
            'success' => true,
            'data' => $orders
        ]);
    }

    public function addTracking(Request $request)
    {
        $request->validate([
            'order_id' => 'required|exists:orders,id',
            'tracking_number' => 'required|string|max:255',
        ]);

        try {
            $order = Order::findOrFail($request->order_id);

            // Update order status and tracking number
            $order->update([
                'status' => 'shipped',
                'tracking_number' => $request->tracking_number,
            ]);

            // Call ShipStation API to update tracking
            $shipStationService = new shipStationService();
            Log::info('[ShipStation Tracking] Manual tracking UI triggering ShipStation update', [
                'order_id' => $order->id,
                'marketplace_order_id' => $order->order_id,
                'tracking_number' => $request->tracking_number,
            ]);

            $shipStationResult = $shipStationService->shipOrder($order, $request->tracking_number);

            if ($shipStationResult) {
                Log::info('[ShipStation Tracking] Manual tracking UI ShipStation result', [
                    'order_id' => $order->id,
                    'marketplace_order_id' => $order->order_id,
                    'tracking_number' => $request->tracking_number,
                    'shipstation_updated' => true,
                ]);
            } else {
                Log::error('[ShipStation Tracking] Manual tracking UI ShipStation result — FAILED (see previous [ShipStation Tracking] error for reason)', [
                    'order_id' => $order->id,
                    'marketplace_order_id' => $order->order_id,
                    'tracking_number' => $request->tracking_number,
                    'shipstation_updated' => false,
                ]);
            }

            if (!$shipStationResult) {
                return response()->json([
                    'success' => false,
                    'message' => 'Order updated locally but ShipStation API call failed. Check logs for details.',
                ], 500);
            }

            return response()->json([
                'success' => true,
                'message' => 'Tracking number added successfully and ShipStation updated.',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to add tracking: ' . $e->getMessage(),
            ], 500);
        }
    }

    
}


