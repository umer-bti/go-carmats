<?php

namespace App\Http\Controllers\Console\BatchManagement;

use App\Http\Controllers\Controller;
use App\Models\Batch;
use App\Models\BatchDesign;
use App\Models\BatchOrder;
use App\Models\ProductSetting;
use App\Models\File;
use App\Models\Order;
use App\Services\ShippingLabelService;
use App\Services\VeeqoService;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Yajra\DataTables\DataTables;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use ZipArchive;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\CustomOrdersExport;

class BatchController extends Controller
{
    public $shippingLabelService;
    public $veeqoService;

    public function __construct(ShippingLabelService $shippingLabelService, VeeqoService $veeqoService)
    {
        $this->shippingLabelService = $shippingLabelService;
        $this->veeqoService = $veeqoService;
    }

    public function index(Request $request)
    {
        if ($request->ajax()) {
            $status = $request->input('status', 'active');
            $batches = Batch::with(['orders' => function ($query) {
                $query->select('orders.id', 'orders.source');
            }])->withCount('orders')->where('status', $status);

            return DataTables::of($batches)
                ->addIndexColumn()
                ->addColumn('action', function ($row) {
                    $hasAmazonPrime = $row->orders->contains('source', 'Amazon_Prime');

                    $processButton = '';
                    if (! $hasAmazonPrime) {
                        if ($row->batch_process == 'processed') {
                            $processButton = '
                            <button type="button" class="btn btn-sm btn-secondary" disabled>
                                Label Processed
                            </button>
                            ';
                        } else {
                            $processButton = '
                                <button type="button" class="btn btn-sm btn-info" onclick="markAsProcessed(this)" data-id="' . $row->id . '">
                                    Process label
                                </button>
                            ';
                        }
                    }
                    return '
                        <div class="d-flex gap-1">
                            <a href="' . route('console.batchManagement.completed.process', ['batch_id' => $row->id]) . '" class="btn btn-sm btn-warning">
                                Design
                            </a>
                            <button type="button" class="btn btn-sm btn-info" onclick="setShowBatchModalData(this)" data-id="' . $row->id . '">
                                View
                            </button>
                            <button type="button" class="btn btn-sm btn-success" onclick="downloadBatchFiles(this)" data-id="' . $row->id . '">
                                <i class="fas fa-file"></i> Show files
                            </button>
                            <button type="button" class="btn btn-sm btn-primary" onclick="exportBatchOrders(this)" data-id="' . $row->id . '" data-batch-name="' . e($row->batch_name) . '">
                                <i class="fas fa-file-excel"></i> Export Orders
                            </button>
                            <button type="button" class="btn btn-sm btn-secondary" onclick="markAsCompleted(this)" data-id="' . $row->id . '">
                                Mark as completed
                            </button>
                              ' . $processButton . '
                        </div>
                    ';
                })
                ->rawColumns(['action'])
                ->make(true);
        } else {
            return view('console.batch-management.batches.index');
        }
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'batch_id' => 'required|unique:batches,batch_id',
            'batch_name' => 'required|string',
            'type_setting' => 'required|boolean',
            'order_ids' => 'required|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $validator->errors()
            ]);
        }

        $validated = $validator->validated();

        // Create the batch
        $batch = Batch::create([
            'batch_id' => $validated['batch_id'],
            'batch_name' => $validated['batch_name'],
        ]);

        foreach ($validated['order_ids'] as $index => $orderId) {
            BatchOrder::create([
                'batch_id' => $batch->id,
                'order_id' => $orderId,
                'sort_order' => $index + 1,
            ]);
        }

        if ($request->type_setting == true) {
            return response()->json([
                'success' => true,
                'redirect_url' => route('console.batchManagement.completed.process', ['batch_id' => $batch->id]),
                'message' => 'Batch created successfully. Design process started.'
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Batch created successfully.'
        ]);
    }

    public function generateUniqueIdentifiers()
    {
        // Generate a unique 9-digit batch ID
        do {
            $batchId = 'B-' . rand(100000000000000, 999999999999999);
        } while (Batch::where('batch_id', $batchId)->exists());

        // Format batch name like '23Jun202510:45'
        $now = now();
        $batchName = $now->format('d') . $now->format('M') . $now->format('Y') . $now->format('H:i');

        return response()->json([
            'success' => true,
            'data' => [
                'batch_id' => $batchId,
                'batch_name' => $batchName,
            ]
        ]);
    }

    public function checkProductFiles(Request $request)
    {
        $productSkus = $request->input('products', []);
        
        // Get products that have files uploaded
        $productsWithFiles = ProductSetting::where(function ($query) use ($productSkus) {
            foreach ($productSkus as $sku) {
                $query->orWhereJsonContains('skus', $sku);
            }
        })
            ->whereHas('files', function ($query) {
                $query->where('tag', 'product_dxf');
            })
            ->pluck('skus')
            ->toArray();
        // Flatten the result because each 'skus' is an array
        $productsWithFiles = Arr::flatten($productsWithFiles);
        
        // Create a map of product names to their file status
        $productFileStatus = [];
        foreach ($productSkus as $productSku) {
            $productFileStatus[$productSku] = in_array($productSku, $productsWithFiles);
        }
        
        return response()->json([
            'success' => true,
            'data' => $productFileStatus
        ]);
    }

    public function markAsCompleted(Request $request)
    {
        $batch = Batch::find($request->input('batch_id'));
        
        if (!$batch) {
            return response()->json([
                'success' => false,
                'message' => 'Batch not found.',
            ]);
        }

        // Update batch status to completed
        $batch->update(['status' => 'completed']);

        // Create batch design record
        BatchDesign::create([
            'batch_id' => $request->input('batch_id'),
            'design_status' => 'Processed',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Batch marked as completed successfully.',
        ]);
    }

    public function markAsProcessed(Request $request)
    {
        $batch = Batch::with('orders')->find($request->input('batch_id'));

        if (! $batch) {
            return response()->json([
                'success' => false,
                'message' => 'Batch not found.',
            ]);
        }

        $processedCount = 0;
        $failedOrders = [];

        foreach ($batch->orders as $order) {
            if ($order->files) {
                continue;
            }
            $labelService = $order->source === 'Amazon_Prime'
                ? $this->veeqoService
                : $this->shippingLabelService;

            try {
                $response = $labelService->generateLabel($order);
                $data = method_exists($response, 'getData') ? $response->getData(true) : null;

                if (is_array($data) && ($data['success'] ?? false)) {
                    $processedCount++;
                    continue;
                }

                $failedOrders[] = [
                    'order_id' => $order->order_id,
                    'item_id' => $order->order_item_id,
                    'message' => $data['error'] ?? $data['message'] ?? 'Unknown label generation error.',
                ];
            } catch (\Throwable $e) {
                Log::error('Batch label generation failed', [
                    'batch_id' => $batch->id,
                    'order_id' => $order->order_id,
                    'item_id' => $order->order_item_id,
                    'source' => $order->source,
                    'error' => $e->getMessage(),
                ]);

                $failedOrders[] = [
                    'order_id' => $order->order_id,
                    'item_id' => $order->order_item_id,
                    'message' => $e->getMessage(),
                ];
            }
        }

        $batch->update(['batch_process' => 'processed']);

        return response()->json([
            'success' => true,
            'message' => empty($failedOrders)
                ? 'Batch Processed successfully.'
                : 'Batch processed with some label failures.',
            'processed_count' => $processedCount,
            'failed_orders' => $failedOrders,
        ]);
    }
    
    public function markAsIncomplete(Request $request)
    {
        $batch = Batch::find($request->input('batch_id'));
        
        if (!$batch) {
            return response()->json([
                'success' => false,
                'message' => 'Batch not found.',
            ]);
        }

        // Update batch status to active
        $batch->update(['status' => 'active']);
        
        // Optional: Remove the batch design record
        BatchDesign::where('batch_id', $request->input('batch_id'))->delete();

        return response()->json([
            'success' => true,
            'message' => 'Batch marked as incomplete successfully.',
        ]);
    }

    public function show($id)
    {
        $batch = Batch::with('orders')->find($id);

        if (!$batch) {
            return response()->json(['success' => false, 'message' => 'Batch not found.']);
        }


        foreach ($batch->orders as $order) {
            $productSetting = ProductSetting::whereJsonContains('skus',$order->sku)->first();

                // Try to get the converted image first, fallback to DXF file or default image
                $order->design_file_url =$productSetting ? asset('storage/' . $productSetting->imageFile->path)
                                                         : asset('themes/console/assets/img/pages/mat.jpg');



        }

        return response()->json([
            'success' => true,
            'batch' => $batch,
        ]);
    }

    public function downloadFiles($id)
    {
        $batch = Batch::with('orders')->find($id);

        if (!$batch) {
            return response()->json(['success' => false, 'message' => 'Batch not found.'], 404);
        }

        // Get all product settings with DXF files


        $files = [];
        foreach ($batch->orders as $key=>$order) {
            $normalizedProduct = preg_replace('/\s+/', ' ', trim($order->make_model));
            $productSetting = ProductSetting::whereJsonContains('skus',$order->sku)->first();


            if ($productSetting && $productSetting->dxfFile) {
                $index=$key+1;
                for($i=0; $i< $order->quantity; $i++){
                    $files[] = [
                        'path' => storage_path('app/public/' . $productSetting->dxfFile->path),
                        'name' => ($index).'-'.$normalizedProduct.'-'.$order->id.'-'.($i+1).'.dxf'
                    ];
                }

            }
        }


        if (empty($files)) {
            return  redirect()->back()->with('error', 'No files found for the products in this batch.');
        }

        // Create ZIP
        $zipFileName = $batch->batch_name.'.zip';
        $zipPath = storage_path('app/public/' . $zipFileName);

        $zip = new ZipArchive;
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) === TRUE) {
            foreach ($files as $file) {
                if (file_exists($file['path'])) {
                    $zip->addFile($file['path'], $file['name']);
                }
            }
            $zip->close();
        }

        // Return the ZIP file for download
        return response()->download($zipPath)->deleteFileAfterSend(true);
    }

    public function exportBatchOrders(Request $request, $id)
    {
        $batch = Batch::with('orders')->findOrFail($id);
        
        // Get all order IDs from this batch
        $orderIds = $batch->orders->pluck('id')->toArray();
        
        // Build query for orders in this batch
        $query = Order::query()->whereIn('id', $orderIds);
        
        // Get batch name for filename
        $batchName = preg_replace('/[^A-Za-z0-9_\-]/', '_', $batch->batch_name);
        $filename = 'batch_orders_' . $batchName . '_' . date('Y-m-d_His') . '.xlsx';
        
        return Excel::download(new CustomOrdersExport($query), $filename);
    }

}
