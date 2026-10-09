<?php

namespace App\Http\Controllers\Console\BatchManagement;

use App\Http\Controllers\Controller;
use App\Models\Batch;
use App\Models\BatchDesign;
use App\Models\ProductSetting;
use App\Services\BatchReorder\BatchListingReorderService;
use App\Services\BatchReorder\BatchOrderSortService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;
use Yajra\DataTables\DataTables;

class CompletedController extends Controller
{
    public function __construct(
        protected BatchListingReorderService $batchListingReorderService,
        protected BatchOrderSortService $batchOrderSortService
    ) {}
    public function index(Request $request)
    {
        if ($request->ajax()) {
            $completedDesigns = BatchDesign::with('batch.orders');

            return DataTables::of($completedDesigns)
                ->addIndexColumn()
                ->addColumn('batch_id', fn($row) => $row->batch?->batch_id ?? '-')
                ->addColumn('batch_name', fn($row) => $row->batch?->batch_name ?? '-')
                ->addColumn('products', fn($row) => $row->batch?->orders->count() ?? 0)
                ->addColumn('status', fn($row) => 'Successfully ' . ($row->design_status ?? '-'))
                ->addColumn('action', function ($row) {
                    if (!$row->batch) {
                        return '<div class="text-center">No actions available</div>';
                    }
                    
                    return '
                        <div class="d-flex gap-1">
                            <button type="button" class="btn btn-sm btn-info" onclick="setShowBatchModalData(this)" data-id="' . $row->batch->id . '">
                                View
                            </button>
                            <button type="button" class="btn btn-sm btn-success" onclick="downloadBatchFiles(this)" data-id="' . $row->batch->id . '">
                                <i class="fas fa-file"></i> Show files
                            </button>
                            <button type="button" class="btn btn-sm btn-warning" onclick="markAsIncomplete(this)" data-id="' . $row->batch->id . '">
                                Mark as Incomplete
                            </button>
                        </div>
                    ';
                })
                ->rawColumns(['action'])
                ->make(true);
        } else {
            return view('console.batch-management.completed.index');
        }
    }

    public function store(Request $request)
    {
        BatchDesign::create([
            'batch_id' => $request->batch_id,
            'design_status' => 'Processed',
        ]);
        return redirect()->route('console.batchManagement.completed.index')
            ->with('success', 'Batch design processed successfully.');
    }

    public function process(Request $request)
    {

        $batch = Batch::with('orders')->findOrFail($request->batch_id);


        foreach ($batch->orders as $order) {
            $productSetting = ProductSetting::whereJsonContains('skus', $order->sku)->first();

            // Attach found record or empty object
            $order->product_setting = $productSetting ?: null;
        }

        return view('console.batch-management.completed.process', compact('batch'));
    }

    public function uploadAndReorder(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'batch_id' => 'required|integer|exists:batches,id',
            'upload_file' => 'required|file|mimes:jpg,jpeg,png,webp,txt|max:10240',
        ]);

        $batch = Batch::with('orders')->findOrFail($validated['batch_id']);

        foreach ($batch->orders as $order) {
            $productSetting = ProductSetting::whereJsonContains('skus', $order->sku)->first();
            $order->product_setting = $productSetting ?: null;
        }

        try {
            $result = $this->batchListingReorderService->process(
                $request->file('upload_file'),
                $batch->orders,
                $batch->id
            );

            $this->batchOrderSortService->saveOrder($batch, $result['ordered_ids']);

            $batch->load('orders');

            $ordersById = $batch->orders->keyBy('id');
            $orderedOrders = collect($result['ordered_ids'])
                ->map(function (int $orderId) use ($ordersById) {
                    $order = $ordersById->get($orderId);

                    if (! $order) {
                        return null;
                    }

                    $productSetting = $order->product_setting;

                    return [
                        'id' => $order->id,
                        'order_id' => $order->order_id,
                        'make_model' => $order->make_model,
                        'material_type' => $productSetting->material_type ?? $order->material_type,
                        'quantity' => $order->quantity,
                        'edging' => $order->edging,
                        'no_of_clips' => $productSetting->no_of_clips ?? null,
                        'no_of_mats' => $productSetting->no_of_mats ?? null,
                        'note' => $productSetting->note ?? null,
                        'order_date' => $order->date,
                        'sku' => $order->sku,
                        'source' => $order->source,
                        'image_url' => $productSetting
                            ? ($productSetting->imageFile
                                ? asset('storage/'.$productSetting->imageFile->path)
                                : ($productSetting->dxfFile
                                    ? asset('storage/'.$productSetting->dxfFile->path)
                                    : asset('themes/console/assets/img/pages/mat.jpg')))
                            : asset('themes/console/assets/img/pages/mat.jpg'),
                    ];
                })
                ->filter()
                ->values();

            return response()->json([
                'success' => true,
                'message' => 'Listings reordered successfully.',
                'ordered_orders' => $orderedOrders,
                'summary' => $result['summary'],
                'matches' => $result['matches'],
            ]);
        } catch (Throwable $exception) {
            Log::error('Batch listing reorder failed', [
                'batch_id' => $validated['batch_id'],
                'error' => $exception->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
            ], 422);
        }
    }
}
