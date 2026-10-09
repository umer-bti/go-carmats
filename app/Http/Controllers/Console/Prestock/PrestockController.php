<?php

namespace App\Http\Controllers\Console\Prestock;

use App\Http\Controllers\Controller;
use App\Http\Concerns\VerifiesAccessPassword;
use App\Models\Prestock;
use App\Models\ProductSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Milon\Barcode\DNS1D;
use Yajra\DataTables\Facades\DataTables;

class PrestockController extends Controller
{
    use VerifiesAccessPassword;

    public function index(Request $request)
    {
        if ($request->ajax()) {
            $query = Prestock::query()
                ->join('product_settings', 'product_settings.id', '=', 'prestocks.product_setting_id')
                ->with('productSetting')
                ->select('prestocks.*');

            return DataTables::of($query)
                ->addIndexColumn()
                ->addColumn('product_name', function ($row) {
                    return e($row->productSetting?->name ?? '—');
                })
                ->editColumn('material', fn ($row) => e($row->material ?? '—'))
                ->editColumn('stock', fn ($row) => (int) $row->stock)
                ->editColumn('comment', function ($row) {
                    $c = $row->comment ?? '';
                    if ($c === '') {
                        return '';
                    }
                    if (Str::length($c) > 80) {
                        return '<span title="' . e($c) . '">' . e(Str::limit($c, 80)) . '</span>';
                    }

                    return e($c);
                })
                ->filterColumn('product_name', function ($query, $keyword) {
                    $query->where('product_settings.name', 'like', '%'.$keyword.'%');
                })
                ->filterColumn('material', function ($query, $keyword) {
                    $query->where('prestocks.material', 'like', '%'.$keyword.'%');
                })
                ->orderColumn('product_name', function ($query, $order) {
                    $query->orderBy('product_settings.name', $order);
                })
                ->orderColumn('material', function ($query, $order) {
                    $query->orderBy('prestocks.material', $order);
                })
                ->orderColumn('stock', function ($query, $order) {
                    $query->orderBy('prestocks.stock', $order);
                })
                ->addColumn('action', function ($row) {
                    $edit = '';
                    $del = '';
                    if (auth()->user()->can('edit prestock')) {
                        $edit = '<button type="button" class="btn btn-sm btn-primary" onclick="openEditPrestockModal(this)" data-id="' . $row->id . '">Edit</button>';
                    }
                    if (auth()->user()->can('delete prestock')) {
                        $del = '<button type="button" class="btn btn-sm btn-danger" onclick="confirmDeletePrestock(this)" data-id="' . $row->id . '">Delete</button>';
                    }
                    if (auth()->user()->can('edit prestock')) {
                        $print = '<button type="button" class="btn btn-sm btn-warning" onclick="openPrintLabelModal(' . $row->id . ')">
                            Print Label
                        </button>';
                    }

                    return '<div class="d-flex gap-1 flex-nowrap align-items-center">' . $edit . $del . $print . '</div>';
                })
                ->rawColumns(['comment', 'action'])
                ->make(true);
        }

        $products = ProductSetting::orderBy('name')->get(['id', 'name']);

        return view('console.prestock.index', compact('products'));
    }

    public function show(Prestock $prestock)
    {
        $prestock->load('productSetting');

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $prestock->id,
                'product_setting_id' => $prestock->product_setting_id,
                'stock' => (int) $prestock->stock,
                'comment' => $prestock->comment,
                'product_name' => $prestock->productSetting?->name,
                'material' => $prestock->material,
            ],
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'product_setting_id' => 'required|exists:product_settings,id',
            'stock' => 'required|integer|min:0',
            'material' => 'required|string|in:Carpet,Rubber',
            'comment' => 'nullable|string|max:2000',
        ]);

       $prestock = Prestock::create([
            'product_setting_id' => $request->input('product_setting_id'),
            'stock' => $request->input('stock'),
            'material' => $request->input('material'),
            'comment' => $request->input('comment'),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Prestock added successfully.',
            'data' => [
                'id' => $prestock->id,
            ],
        ]);
    }

    public function update(Request $request, Prestock $prestock)
    {
        $request->validate([
            'product_setting_id' => 'required|exists:product_settings,id',
            'stock' => 'required|integer|min:0',
            'material' => 'required|string|in:Carpet,Rubber',
            'comment' => 'nullable|string|max:2000',
        ]);

        $prestock->update([
            'product_setting_id' => $request->input('product_setting_id'),
            'stock' => $request->input('stock'),
            'material' => $request->input('material'),
            'comment' => $request->input('comment'),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Prestock updated successfully.',
        ]);
    }

    public function destroy(Request $request, Prestock $prestock)
    {
        $request->validate([
            'password' => 'required|string',
        ]);

        if ($denied = $this->denyUnlessAccessPassword($request)) {
            return $denied;
        }

        $prestock->delete();

        return response()->json([
            'success' => true,
            'message' => 'Prestock deleted successfully.',
        ]);
    }

    public function getLabelData($id)
    {
        $prestock = Prestock::with('productSetting.imageFile')->findOrFail($id);

        // $barcodeGenerator = new DNS1D();

        return response()->json([
            'id' => $prestock->id,
            'product_name' => $prestock->productSetting?->name ?? '—',
            'material' => $prestock->material ?? '—',
            'stock' => (int) $prestock->stock,
            'comment' => $prestock->comment ?? '',

            // 'barcode' => $barcodeGenerator->getBarcodePNG((string) $prestock->id, 'C128', 1, 50),

            'image' => $prestock->productSetting?->imageFile
                ? asset('storage/' . $prestock->productSetting->imageFile->path)
                : asset('themes/console/assets/img/pages/mat.jpg'),
        ]);
    }
}
