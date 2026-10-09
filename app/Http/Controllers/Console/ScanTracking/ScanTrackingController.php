<?php

namespace App\Http\Controllers\Console\ScanTracking;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Yajra\DataTables\DataTables;

class ScanTrackingController extends Controller
{
    public function index(Request $request)
    {
        if ($request->ajax()) {
            return $this->datatableResponse($request);
        }

        [$start, $end, $periodLabel, $isDefaultPeriod, $fromDate, $toDate] = $this->resolveDateRange($request);
        $rows = Order::dailyGroupedShippingProviderScanRows($start, $end);
        $totals = $this->sumProviderTotals($rows);

        return view('console.scan-tracking.index', [
            'totalScans' => $totals['total'],
            'providerTotals' => $totals,
            'fromDate' => $fromDate,
            'toDate' => $toDate,
            'periodLabel' => $periodLabel,
            'isDefaultPeriod' => $isDefaultPeriod,
        ]);
    }

    private function datatableResponse(Request $request)
    {
        [$start, $end] = $this->resolveDateRange($request);
        $rows = Order::dailyGroupedShippingProviderScanRows($start, $end);
        $totals = $this->sumProviderTotals($rows);

        return DataTables::of(collect($rows))
            ->addIndexColumn()
            ->with([
                'totalScans' => $totals['total'],
                'providerTotals' => $totals,
            ])
            ->make(true);
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array{evri: int, veeqo: int, shipmate: int, total: int}
     */
    private function sumProviderTotals(array $rows): array
    {
        $collection = collect($rows);

        return [
            'evri' => (int) ($collection->sum('evri') + $collection->sum('evri_additional')),
            'veeqo' => (int) ($collection->sum('veeqo') + $collection->sum('veeqo_additional')),
            'shipmate' => (int) ($collection->sum('shipmate') + $collection->sum('shipmate_additional')),
            'total' => (int) ($collection->sum('total') + $collection->sum('total_additional')),
        ];
    }

    /**
     * @return array{0: Carbon, 1: Carbon, 2: string, 3: bool, 4: ?Carbon, 5: ?Carbon}
     */
    private function resolveDateRange(Request $request): array
    {
        $hasCustomRange = $request->filled('from_date') && $request->filled('to_date');

        if ($hasCustomRange) {
            $fromDate = Carbon::parse($request->input('from_date'));
            $toDate = Carbon::parse($request->input('to_date'));

            if ($fromDate->gt($toDate)) {
                [$fromDate, $toDate] = [$toDate, $fromDate];
            }

            return [
                $fromDate->copy()->startOfDay(),
                $toDate->copy()->endOfDay(),
                $fromDate->format('j M Y') . ' – ' . $toDate->format('j M Y'),
                false,
                $fromDate,
                $toDate,
            ];
        }

        $fromDate = now()->subDays(29)->startOfDay();
        $toDate = now()->endOfDay();

        return [
            $fromDate->copy(),
            $toDate->copy(),
            'Last 30 days',
            true,
            $fromDate,
            $toDate,
        ];
    }
}
