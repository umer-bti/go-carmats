<?php

namespace App\Http\Controllers\Console\Order;

use App\Http\Controllers\Controller;
use App\Http\Concerns\VerifiesAccessPassword;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Imports\OrderImport;
use App\Imports\MultiSheetOrderImport;
use App\Imports\DynamicOrderImport;
use App\Imports\FormatTwoOrderImport;
use App\Models\Order;
use App\Models\Prestock;
use App\Models\ProductSetting;
use App\Models\ShipstationSetting;
use App\Models\Stitcher;
use Maatwebsite\Excel\Facades\Excel;
use Yajra\DataTables\DataTables;
use App\Exports\OrdersExport;
use App\Exports\CustomOrdersExport;
use App\Exports\FormatTwoTemplateExport;

class OrderController extends Controller
{
    use VerifiesAccessPassword;

    public function index(Request $request)
    {
        if ($request->ajax()) {
            $query = Order::query()->with(['stitcher', 'amazonReturn', 'prestock']);

            // Filter by material type (Carpet or Rubber)
            if ($request->filled('material_type')) {
                $query->where('material_type', $request->input('material_type'));
            }

            // Filter by date range
            if ($request->filled('start_date')) {
                $query->whereDate('date', '>=', $request->input('start_date'));
            }
            
            if ($request->filled('end_date')) {
                $query->whereDate('date', '<=', $request->input('end_date'));
            }

            if ($request->filled('scan_start_date')) {
                $query->whereDate('scan_time', '>=', $request->input('scan_start_date'));
            }

            if ($request->filled('scan_end_date')) {
                $query->whereDate('scan_time', '<=', $request->input('scan_end_date'));
            }

            // Filter by order status (pending vs all)
            if ($request->filled('status_filter') && $request->input('status_filter') === 'pending') {
                $query->whereDoesntHave('batchOrders');
            }

            // Filter by ShipStation/Order status field
            if ($request->filled('ship_status')) {
                $query->where('status', $request->input('ship_status'));
            }

            // Filter by design status (whether SKU exists in product_settings)
            if ($request->filled('design_status')) {
                if ($request->input('design_status') === 'designed') {
                    $query->whereExists(function ($subQuery) {
                        $subQuery->select(DB::raw(1))
                            ->from('product_settings')
                            ->whereRaw('JSON_CONTAINS(product_settings.skus, JSON_QUOTE(orders.sku))');
                    });
                } elseif ($request->input('design_status') === 'not_designed') {
                    $query->whereNotExists(function ($subQuery) {
                        $subQuery->select(DB::raw(1))
                            ->from('product_settings')
                            ->whereRaw('JSON_CONTAINS(product_settings.skus, JSON_QUOTE(orders.sku))');
                    });
                }
            }

            // Filter by return / prestock match status (Amazon return vs prestock are separate)
            if ($request->filled('return_status')) {
                if ($request->input('return_status') === 'returned') {
                    $query->whereNotNull('amazon_return_id');
                } elseif ($request->input('return_status') === 'not_returned') {
                    $query->whereNull('amazon_return_id')->whereNull('prestock_id');
                } elseif ($request->input('return_status') === 'prestock') {
                    $query->whereNotNull('prestock_id')->whereNull('amazon_return_id');
                }
            }

            if ($request->filled('stitcher_id')) {
                $stitcherId = $request->input('stitcher_id');
                if ($stitcherId === 'unassigned') {
                    $query->whereNull('stitcher_id');
                } elseif (ctype_digit((string) $stitcherId)) {
                    $query->where('stitcher_id', (int) $stitcherId);
                }
            }

            $this->applySourceFilter($query, $request->input('source_filter'));
            if ($request->filled('shipstation_setting_id') && ctype_digit((string) $request->input('shipstation_setting_id'))) {
                $query->where('shipstation_setting_id', (int) $request->input('shipstation_setting_id'));
            }

            return DataTables::of($query)
                ->addIndexColumn()
                ->editColumn('date', function ($row) {
                    $raw = $row->getAttributes()['date'] ?? null;

                    return $raw
                        ? Carbon::parse($raw)->format('d-m-Y H:i')
                        : '—';
                })
                ->editColumn('scan_time', function ($row) {
                    return $row->scan_time
                        ? $row->scan_time->format('d-m-Y H:i')
                        : '—';
                })
                ->addColumn('stitcher_name', function ($row) {
                    return $row->stitcher?->name ?? 'Unassigned';
                })
                ->orderColumn('stitcher_name', function ($query, $order) {
                    $dir = strtolower($order) === 'desc' ? 'desc' : 'asc';
                    $query->orderBy(
                        Stitcher::query()
                            ->select('name')
                            ->whereColumn('stitchers.id', 'orders.stitcher_id')
                            ->limit(1),
                        $dir
                    );
                })
                ->filterColumn('stitcher_name', function ($query, $keyword) {
                    $keyword = trim((string) $keyword);
                    if ($keyword === '') {
                        return;
                    }
                    if (mb_strtolower($keyword) === 'unassigned') {
                        $query->whereNull('stitcher_id');

                        return;
                    }
                    $query->whereHas('stitcher', function ($q) use ($keyword) {
                        $q->where('name', 'like', '%'.$keyword.'%');
                    });
                })
                ->addColumn('checkbox', function ($row) {
                    return '<input type="checkbox" class="form-check-input row-checkbox"
                        value="' . $row->id . '"
                        data-order-id="' . $row->order_id . '"
                        data-sku="' . $row->sku . '"
                        data-design-type="' . $row->order_item_id . '"
                        data-product="' . e($row->make_model) . '">';
                })
                ->addColumn('batch_status', function ($row) {
                    // Check if this order is part of any batch
                    $hasBatch = $row->batches()->first();
                    
                    if ($hasBatch) {
                        return '<span class="badge bg-success">Processed ('.$hasBatch->batch_name.') </span>';
                    } else {
                        return '<span class="badge bg-warning">Pending</span>';
                    }
                })
                ->addColumn('action', function ($row) {
                    $buttons = '';

                    if ($row->amazon_return_id && $row->amazonReturn) {
                        $buttons .= '<button class="btn btn-sm btn-warning me-1" onclick="showReturnDetails(this)" data-order-id="' . $row->id . '" data-return-id="' . $row->amazon_return_id . '"><i class="fas fa-undo me-1"></i>Return</button>';
                        $buttons .= '<button class="btn btn-sm btn-secondary me-1" onclick="unutilizeReturn(this)" data-order-id="' . $row->id . '" data-match-type="amazon"><i class="fas fa-unlink me-1"></i>Unutilize</button>';
                        $matchLabel = ($row->sku == $row->amazonReturn->sku) ? 'SKU' : 'Name &amp; Material';
                        $buttons .= '<button type="button" class="btn btn-sm btn-info me-1"><i class="fas fa-eye me-1"></i>' . $matchLabel . '</button>';
                    } elseif ($row->prestock_id && $row->prestock) {
                        $buttons .= '<button class="btn btn-sm btn-warning me-1" onclick="showPrestockMatchDetails(this)" data-order-id="' . $row->id . '"><i class="fas fa-box me-1"></i>Prestock</button>';
                        $buttons .= '<button class="btn btn-sm btn-secondary me-1" onclick="unutilizeReturn(this)" data-order-id="' . $row->id . '" data-match-type="prestock"><i class="fas fa-unlink me-1"></i>Unutilize</button>';
                        $buttons .= '<button type="button" class="btn btn-sm btn-info me-1"><i class="fas fa-eye me-1"></i>Prestock</button>';
                    }

                    $buttons .= '<button class="btn btn-sm btn-danger" onclick="deleteOrder(event,this)" data-id="' . $row->id . '">Delete</button>';

                    return $buttons;
                })
                ->setRowClass(function ($row) {
                    $rowClasses = [];

                    if ($row->source === 'Amazon_Prime') {
                        $rowClasses[] = 'amazon-prime-order';
                    }

                    if ($row->amazon_return_id) {
                        $rowClasses[] = 'returned-order';
                    } elseif ($row->prestock_id) {
                        $rowClasses[] = 'prestock-order';
                    }

                    return implode(' ', $rowClasses);
                })
                ->rawColumns(['checkbox', 'batch_status', 'action'])
                ->make(true);
        } else {
            // Get orders grouped by material type for tabs
            $carpetOrders = Order::where('material_type', 'Carpet')->select('order_id', 'make_model')->distinct()->get();
            $rubberOrders = Order::where('material_type', 'Rubber')->select('order_id', 'make_model')->distinct()->get();
            $stitchers = Stitcher::query()->orderBy('name')->get();
            $shipstationSettings = ShipstationSetting::where('is_active', true)->get();
            $orderSources = $this->getDistinctOrderSources();
            $orderSourceOptions = collect($orderSources)
                ->mapWithKeys(fn (string $source) => [$source => $this->formatSourceLabel($source)])
                ->all();
            $hasOrdersWithoutSource = $this->hasOrdersWithoutSource();
            $defaultSourceFilter = $this->defaultSourceFilter();

            return view('console.orders.index', compact(
                'carpetOrders',
                'rubberOrders',
                'stitchers',
                'shipstationSettings',
                'orderSourceOptions',
                'hasOrdersWithoutSource',
                'defaultSourceFilter',
            ));
        }
    }



    public function importOrder(Request $request)
    {
        $request->validate([
            'orderFile' => 'required|mimes:xlsx,csv,xls',
            'import_format' => 'nullable|in:format1,format2',
        ]);

        $file = $request->file('orderFile');
        $productCode = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $importFormat = $request->input('import_format', 'format1');

        try {
            // One transaction for the whole import – any error rolls back all orders
            DB::transaction(function () use ($file, $productCode, $importFormat) {
                if ($importFormat === 'format2') {
                    Excel::import(new FormatTwoOrderImport($productCode), $file);
                } else {
                    Excel::import(new DynamicOrderImport($productCode), $file);
                }
            });
        } catch (\Throwable $e) {
            $message = 'Import failed. No orders were saved. ' . $e->getMessage();
            if (request()->wantsJson() || request()->ajax()) {
                return response()->json(['success' => false, 'message' => $message], 422);
            }
            return back()->with('error', $message);
        }

        $message = $importFormat === 'format2'
            ? 'Orders imported successfully using Format 2 (two sheets).'
            : 'Orders imported successfully from the sheet with automatic material type detection.';

        return back()->with('success', $message);
    }

    /**
     * Download Format 2 import template (two sheets: Carpet, Rubber; headers only, no orders).
     */
    public function downloadFormat2Template()
    {
        $filename = 'orders_import_format2_template_' . date('Y-m-d') . '.xlsx';
        return Excel::download(new FormatTwoTemplateExport(), $filename);
    }

    public function destroy($id)
    {
        $order = Order::find($id);

        if ($order) {
            $order->delete();
            return response()->json(['success' => true, 'message' => 'Order deleted successfully.'], 200);
        } else {
            return response()->json(['success' => false, 'message' => 'Order not found.'], 404);
        }
    }

    public function bulkDestroy(Request $request)
    {
        $request->validate([
            'order_ids' => 'required|array',
            'order_ids.*' => 'integer|exists:orders,id',
            'password' => 'required|string',
        ]);

        if ($denied = $this->denyUnlessAccessPassword($request)) {
            return $denied;
        }

        try {
            $orderIds = $request->input('order_ids');
            $deletedCount = Order::whereIn('id', $orderIds)->delete();

            if ($deletedCount > 0) {
                return response()->json([
                    'success' => true, 
                    'message' => "Successfully deleted {$deletedCount} order(s)."
                ], 200);
            } else {
                return response()->json([
                    'success' => false, 
                    'message' => 'No orders were deleted.'
                ], 400);
            }
        } catch (\Exception $e) {
            return response()->json([
                'success' => false, 
                'message' => 'An error occurred while deleting orders: ' . $e->getMessage()
            ], 500);
        }
    }

    public function getReturnDetails(Request $request)
    {
        $order = Order::with('amazonReturn')->find($request->order_id);

        if (!$order || !$order->amazonReturn) {
            return response()->json([
                'success' => false,
                'message' => 'Return not found.'
            ], 404);
        }

        $amazonReturn = $order->amazonReturn;
        $returnEdging = $amazonReturn->edging;
        if ($returnEdging === null || $returnEdging === '') {
            $returnEdging = $order->edging;
        }

        return response()->json([
            'success' => true,
            'data' => [
                'order' => [
                    'order_id' => $order->order_id,
                    'sku' => $order->sku,
                    'make_model' => $order->make_model,
                    'material_type' => $order->material_type,
                    'quantity' => $order->quantity,
                    'edging' => $order->edging,
                    'recipient_name' => $order->recipient_name,
                    'address' => $order->address,
                    'city' => $order->city,
                    'postal_code' => $order->postal_code,
                ],
                'return' => [
                    'item_name' => $amazonReturn->item_name,
                    'return_sku' => $amazonReturn->sku,
                    'return_material_type' => $amazonReturn->material_type,
                    'edging' => $returnEdging,
                    'return_edging' => $returnEdging,
                    'return_request_date' => $amazonReturn->return_request_date,
                    'reason' => $amazonReturn->reason,
                    'return_type' => $amazonReturn->return_type,
                    'status' => $amazonReturn->status,
                    'tracking' => $amazonReturn->tracking,
                    'received' => $amazonReturn->received,
                    'notes' => $amazonReturn->notes,
                ],
            ],
        ]);
    }

    public function getPrestockMatchDetails(Request $request)
    {
        $request->validate([
            'order_id' => 'required|exists:orders,id',
        ]);

        $order = Order::with(['prestock.productSetting'])->find($request->order_id);

        if (! $order || ! $order->prestock_id || ! $order->prestock) {
            return response()->json([
                'success' => false,
                'message' => 'Prestock match not found.',
            ], 404);
        }

        $prestock = $order->prestock;
        $product = $prestock->productSetting;

        return response()->json([
            'success' => true,
            'data' => [
                'order' => [
                    'order_id' => $order->order_id,
                    'sku' => $order->sku,
                    'make_model' => $order->make_model,
                    'material_type' => $order->material_type,
                    'quantity' => $order->quantity,
                    'edging' => $order->edging,
                ],
                'prestock' => [
                    'product_name' => $product?->name,
                    'material' => $prestock->material,
                    'edging' => $order->edging,
                    'remaining_stock' => (int) $prestock->stock,
                    'comment' => $prestock->comment,
                ],
                'match_basis' => 'Prestock: order product name matched the linked product (from product settings), and order material matched this prestock line’s material; one unit was reserved when the order was created. Remaining stock is the current quantity left on that prestock line.',
            ],
        ]);
    }

    public function unutilizeReturn(Request $request)
    {
        $request->validate([
            'order_id' => 'required|exists:orders,id',
        ]);

        $order = Order::findOrFail($request->order_id);

        if (! $order->amazon_return_id && ! $order->prestock_id) {
            return response()->json([
                'success' => false,
                'message' => 'This order is not linked to a return or prestock.',
            ], 422);
        }

        $message = 'Order updated successfully.';

        DB::transaction(function () use ($order, &$message) {
            $locked = Order::lockForUpdate()->findOrFail($order->id);

            if ($locked->amazon_return_id) {
                $locked->update(['amazon_return_id' => null]);
                $message = 'Order unutilized from return successfully.';
            } elseif ($locked->prestock_id) {
                Prestock::whereKey($locked->prestock_id)->increment('stock');
                $locked->update(['prestock_id' => null]);
                $message = 'Prestock quantity restored and order unlinked.';
            }
        });

        return response()->json([
            'success' => true,
            'message' => $message,
        ]);
    }

    public function customExport(Request $request)
    {

        $query = Order::query();

        // Filter by material type (Carpet or Rubber)
        $materialType = 'All';
        if ($request->filled('material_type')) {
            $materialType = $request->input('material_type');
            $query->where('material_type', $materialType);
        }

        // Filter by date range
        if ($request->filled('start_date')) {
            $query->whereDate('date', '>=', $request->input('start_date'));
        }
        
        if ($request->filled('end_date')) {
            $query->whereDate('date', '<=', $request->input('end_date'));
        }

        if ($request->filled('scan_start_date')) {
            $query->whereDate('scan_time', '>=', $request->input('scan_start_date'));
        }

        if ($request->filled('scan_end_date')) {
            $query->whereDate('scan_time', '<=', $request->input('scan_end_date'));
        }

        // Filter by order status (pending vs all)
        if ($request->filled('status_filter') && $request->input('status_filter') === 'pending') {
            $query->whereDoesntHave('batchOrders');
        }

        // Filter by ShipStation/Order status field
        if ($request->filled('ship_status')) {
            $query->where('status', $request->input('ship_status'));
        }

        // Filter by design status
        if ($request->filled('design_status')) {
            if ($request->input('design_status') === 'designed') {
                $query->whereExists(function ($subQuery) {
                    $subQuery->select(DB::raw(1))
                        ->from('product_settings')
                        ->whereRaw('JSON_CONTAINS(product_settings.skus, JSON_QUOTE(orders.sku))');
                });
            } elseif ($request->input('design_status') === 'not_designed') {
                $query->whereNotExists(function ($subQuery) {
                    $subQuery->select(DB::raw(1))
                        ->from('product_settings')
                        ->whereRaw('JSON_CONTAINS(product_settings.skus, JSON_QUOTE(orders.sku))');
                });
            }
        }

        // Filter by return / prestock match status (Amazon return vs prestock are separate)
        if ($request->filled('return_status')) {
            if ($request->input('return_status') === 'returned') {
                $query->whereNotNull('amazon_return_id');
            } elseif ($request->input('return_status') === 'not_returned') {
                $query->whereNull('amazon_return_id')->whereNull('prestock_id');
            } elseif ($request->input('return_status') === 'prestock') {
                $query->whereNotNull('prestock_id')->whereNull('amazon_return_id');
            }
        }

        if ($request->filled('stitcher_id')) {
            $stitcherId = $request->input('stitcher_id');
            if ($stitcherId === 'unassigned') {
                $query->whereNull('stitcher_id');
            } elseif (ctype_digit((string) $stitcherId)) {
                $query->where('stitcher_id', (int) $stitcherId);
            }
        }

        $this->applySourceFilter($query, $request->input('source_filter'));

        if ($request->filled('shipstation_setting_id') && ctype_digit((string) $request->input('shipstation_setting_id'))) {
            $query->where('shipstation_setting_id', (int) $request->input('shipstation_setting_id'));
        }

        $filename = 'orders_custom_' . strtolower($materialType) . '_' . date('Y-m-d_His') . '.xlsx';
        
        return Excel::download(new CustomOrdersExport($query), $filename);
    }

    private function getDistinctOrderSources(): array
    {
        return Order::query()
            ->whereNotNull('source')
            ->where('source', '!=', '')
            ->distinct()
            ->orderBy('source')
            ->pluck('source')
            ->all();
    }

    private function hasOrdersWithoutSource(): bool
    {
        return Order::query()
            ->where(function ($query) {
                $query->whereNull('source')->orWhere('source', '');
            })
            ->exists();
    }

    private function defaultSourceFilter(): string
    {
        return 'all';
    }

    private function applySourceFilter($query, ?string $sourceFilter): void
    {
        if ($sourceFilter === null || $sourceFilter === '' || $sourceFilter === 'all') {
            return;
        }

        if (in_array($sourceFilter, ['other', 'others'], true)) {
            $query->where(function ($subQuery) {
                $subQuery->whereNull('source')->orWhere('source', '');
            });

            return;
        }

        $query->where('source', $sourceFilter);
    }

    private function formatSourceLabel(string $source): string
    {
        return match (strtolower($source)) {
            'amazon_uk' => 'Amazon Uk',
            'amazon' => 'Amazon',
            'amazon_prime' => 'Amazon Prime',
            'ebay_v2' => 'Ebay V2',
            'tiktok' => 'TikTok',
            default => Str::of($source)->replace(['_', '-'], ' ')->title()->toString(),
        };
    }
}
