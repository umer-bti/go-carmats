<?php

namespace App\Http\Controllers\Console\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\AmazonReturn;
use App\Models\Batch;
use App\Models\Order;
use App\Models\Stitcher;
use Carbon\Carbon;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    /**
     * Gate page for dashboard access password.
     */
    public function gate(Request $request)
    {
        $returnUrl = $request->query('return', route('console.dashboard'));

        return view('console.dashboard.gate', compact('returnUrl'));
    }

    /**
     * Verify dashboard access password and set session.
     */
    public function verifyPassword(Request $request)
    {
        $password = config('settings.access_password', '');
        if ($password === '' || $request->input('password') !== $password) {
            return response()->json(['success' => false, 'message' => 'Incorrect password. Access denied.'], 401);
        }

        session(['dashboard_access_verified' => true]);

        return response()->json(['success' => true]);
    }


    public function index(Request $request)
    {
        $now = Carbon::now();

        // ✅ Single date (default = today)
        $selectedDate = $request->date
            ? Carbon::parse($request->date)
            : $now;

        $start = $selectedDate->copy()->startOfDay();
        $end   = $selectedDate->copy()->endOfDay();

        // —— Order stats ——
        $ordersToday = Order::whereDate('date', $selectedDate)->count();

        $amazonOrdersToday = Order::where('source', 'amazon_uk')
            ->whereDate('date', $selectedDate)
            ->count();

        $ebayOrdersToday = Order::where('source', 'ebay_v2')
            ->whereDate('date', $selectedDate)
            ->count();

        $carpetOrdersToday = Order::where('material_type', 'Carpet')
            ->whereDate('date', $selectedDate)
            ->count();

        $rubberOrdersToday = Order::where('material_type', 'Rubber')
            ->whereDate('date', $selectedDate)
            ->count();

        // —— Scan stats ——
        $scannedToday = Order::whereNotNull('scan_time')
            ->whereBetween('scan_time', [$start, $end])
            ->count();

        // —— Batch stats ——
        $batchesCreatedToday = Batch::whereBetween('created_at', [$start, $end])->count();

        $batchesPending = Batch::where('status', 'active')
            ->whereBetween('created_at', [$start, $end])
            ->count();

        $batchesCompleted = Batch::where('status', 'completed')
            ->whereBetween('updated_at', [$start, $end])
            ->count();

        // —— Returns ——
        $returnsToday = AmazonReturn::whereBetween('created_at', [$start, $end])->count();

        // —— Stitchers (sorted max → min) ——
        $stitchersScanToday = Stitcher::withCount([
            'orders' => function ($q) use ($start, $end) {
                $q->whereNotNull('scan_time')
                    ->whereBetween('scan_time', [$start, $end]);
            },
        ])
            ->orderByDesc('orders_count')
            ->get();

        return view('console.dashboard.index', compact(
            'ordersToday',
            'amazonOrdersToday',
            'ebayOrdersToday',
            'carpetOrdersToday',
            'rubberOrdersToday',
            'scannedToday',
            'batchesCreatedToday',
            'batchesPending',
            'batchesCompleted',
            'returnsToday',
            'stitchersScanToday',
            'selectedDate'
        ));
    }
}
