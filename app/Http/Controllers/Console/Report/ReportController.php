<?php

namespace App\Http\Controllers\Console\Report;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\EvriVerificationService;
use Illuminate\Http\Request;
use Yajra\DataTables\DataTables;

class ReportController extends Controller
{
    public function index(Request $request)
    {
        if ($request->ajax()) {
            $query = Order::query()
                ->with(['files', 'evriShipmentVerification'])
                ->where('generated_by', 'evri');

            if ($request->filled('start_date')) {
                $query->whereDate('date', '>=', $request->input('start_date'));
            }

            if ($request->filled('end_date')) {
                $query->whereDate('date', '<=', $request->input('end_date'));
            }

            if ($request->filled('order_id')) {
                $query->where('order_id', 'like', '%' . $request->input('order_id') . '%');
            }

            if ($request->filled('verification_result')) {
                $result = $request->input('verification_result');
                if ($result === 'pending') {
                    $query->where(function ($q) {
                        $q->whereDoesntHave('evriShipmentVerification')
                            ->orWhereHas('evriShipmentVerification', fn ($vq) => $vq->where('verification_result', 'pending'));
                    });
                } else {
                    $query->whereHas('evriShipmentVerification', fn ($vq) => $vq->where('verification_result', $result));
                }
            }

            if ($request->filled('system_status')) {
                $query->where('status', $request->input('system_status'));
            }

            $verificationService = new EvriVerificationService;

            return DataTables::of($query)
                ->addIndexColumn()
                ->addColumn('order_item_id', fn ($row) => $row->order_item_id ?? '—')
                ->addColumn('system_shipped_label', function ($row) use ($verificationService) {
                    $system = $verificationService->snapshotSystemState($row);

                    return $system['system_shipped']
                        ? '<span class="badge bg-success">Yes</span>'
                        : '<span class="badge bg-secondary">No</span>';
                })
                ->addColumn('system_label_saved', function ($row) use ($verificationService) {
                    $system = $verificationService->snapshotSystemState($row);

                    return $system['system_has_label']
                        ? '<span class="badge bg-success">Yes</span>'
                        : '<span class="badge bg-danger">No</span>';
                })
                ->addColumn('evri_label_exists_label', function ($row) {
                    $verification = $row->evriShipmentVerification;
                    if (! $verification || $verification->evri_label_exists === null) {
                        return '<span class="badge bg-secondary">Not checked</span>';
                    }

                    return $verification->evri_label_exists
                        ? '<span class="badge bg-success">Yes</span>'
                        : '<span class="badge bg-danger">No</span>';
                })
                ->addColumn('evri_status_label', function ($row) {
                    $status = $row->evriShipmentVerification?->evri_status;

                    return $status ? e($status) : '—';
                })
                ->addColumn('verification_badge', function ($row) {
                    $result = $row->evriShipmentVerification?->verification_result ?? 'pending';

                    return match ($result) {
                        'verified' => '<span class="badge bg-success">Verified</span>',
                        'mismatch' => '<span class="badge bg-warning text-dark">Mismatch</span>',
                        'error' => '<span class="badge bg-danger">Error</span>',
                        default => '<span class="badge bg-secondary">Pending</span>',
                    };
                })
                ->addColumn('verified_at', function ($row) {
                    return $row->evriShipmentVerification?->evri_verified_at?->format('d-m-Y H:i') ?? '—';
                })
                ->addColumn('action', function ($row) {
                    return '<button type="button" class="btn btn-sm btn-primary verify-evri-btn" data-id="' . $row->id . '">'
                        . '<i class="fas fa-sync-alt me-1"></i>Verify</button>';
                })
                ->setRowClass(function ($row) {
                    $result = $row->evriShipmentVerification?->verification_result ?? 'pending';

                    return match ($result) {
                        'verified' => 'table-success',
                        'mismatch' => 'table-warning',
                        'error' => 'table-danger',
                        default => '',
                    };
                })
                ->rawColumns(['system_shipped_label', 'system_label_saved', 'evri_label_exists_label', 'verification_badge', 'action'])
                ->make(true);
        }

        $stats = [
            'total' => Order::where('generated_by', 'evri')->count(),
            'verified' => Order::where('generated_by', 'evri')
                ->whereHas('evriShipmentVerification', fn ($q) => $q->where('verification_result', 'verified'))
                ->count(),
            'mismatch' => Order::where('generated_by', 'evri')
                ->whereHas('evriShipmentVerification', fn ($q) => $q->where('verification_result', 'mismatch'))
                ->count(),
            'pending' => Order::where('generated_by', 'evri')
                ->where(function ($q) {
                    $q->whereDoesntHave('evriShipmentVerification')
                        ->orWhereHas('evriShipmentVerification', fn ($vq) => $vq->where('verification_result', 'pending'));
                })
                ->count(),
        ];

        return view('console.reports.index', compact('stats'));
    }

    public function verify(Request $request, EvriVerificationService $verificationService)
    {
        $request->validate([
            'order_id' => 'required|exists:orders,id',
        ]);

        $order = Order::with('files')->findOrFail($request->order_id);
        $verification = $verificationService->verifyOrder($order);

        return response()->json([
            'success' => true,
            'message' => 'Evri verification completed.',
            'data' => [
                'verification_result' => $verification->verification_result,
                'evri_status' => $verification->evri_status,
                'evri_label_exists' => $verification->evri_label_exists,
                'evri_error' => $verification->evri_error,
            ],
        ]);
    }

    public function verifyBulk(Request $request, EvriVerificationService $verificationService)
    {
        $request->validate([
            'order_ids' => 'nullable|array',
            'order_ids.*' => 'integer|exists:orders,id',
        ]);

        $query = Order::query()
            ->with('files')
            ->where('generated_by', 'evri');

        if ($request->filled('order_ids')) {
            $query->whereIn('id', $request->order_ids);
        } else {
            $query->limit(50);
        }

        $processed = 0;
        $errors = 0;

        foreach ($query->get() as $order) {
            $result = $verificationService->verifyOrder($order);
            $processed++;
            if ($result->verification_result === 'error') {
                $errors++;
            }
        }

        return response()->json([
            'success' => true,
            'message' => "Verified {$processed} order(s)." . ($errors ? " {$errors} had errors." : ''),
            'processed' => $processed,
            'errors' => $errors,
        ]);
    }
}
