<?php

namespace App\Http\Controllers\Console\LiveStats;

use App\Http\Controllers\Controller;
use App\Models\Batch;
use App\Models\Order;
use App\Models\Stitcher;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

class LiveStatsController extends Controller
{
    public function index(): View
    {
        $stats = $this->buildTodayStats();

        return view('console.live-stats.index', $stats);
    }

    public function data(): JsonResponse
    {
        return response()->json($this->buildTodayStatsPayload());
    }

    private function buildTodayStats(): array
    {
        $payload = $this->buildTodayStatsPayload();

        return [
            'batchesCreatedToday' => $payload['totalBatches'],
            'scannedToday' => $payload['totalScans'],
            'batchesCompleted' => $payload['completedBatches'],
            'stitchersScanToday' => collect($payload['stitchers'])->map(fn ($row) => (object) [
                'id' => $row['id'],
                'name' => $row['name'],
                'orders_count' => $row['count'],
            ]),
            'stitcherScansTotal' => collect($payload['stitchers'])->sum('count'),
        ];
    }

    private function buildTodayStatsPayload(): array
    {
        $todayStart = now()->startOfDay();
        $todayEnd = now()->endOfDay();

        $batchesCreatedToday = Batch::whereBetween('created_at', [$todayStart, $todayEnd])->count();

        $batchesCompleted = Batch::where('status', 'completed')
            ->whereBetween('updated_at', [$todayStart, $todayEnd])
            ->count();

        $scannedToday = Order::whereNotNull('scan_time')
            ->whereBetween('scan_time', [$todayStart, $todayEnd])
            ->count();

        $stitchersScanToday = Stitcher::withCount([
            'orders' => function ($q) use ($todayStart, $todayEnd) {
                $q->whereNotNull('scan_time')
                    ->whereBetween('scan_time', [$todayStart, $todayEnd]);
            },
        ])
            ->orderByDesc('orders_count')
            ->get();

        return [
            'totalBatches' => $batchesCreatedToday,
            'totalScans' => $scannedToday,
            'completedBatches' => $batchesCompleted,
            'stitchers' => $stitchersScanToday->map(fn ($stitcher) => [
                'id' => $stitcher->id,
                'name' => $stitcher->name,
                'count' => (int) ($stitcher->orders_count ?? 0),
            ])->values()->all(),
        ];
    }
}
