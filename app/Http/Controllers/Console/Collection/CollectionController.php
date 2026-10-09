<?php

namespace App\Http\Controllers\Console\Collection;

use App\Http\Controllers\Controller;
use App\Http\Concerns\VerifiesAccessPassword;
use App\Models\Collection;
use App\Models\ProductSetting;
use Illuminate\Http\Request;
use Yajra\DataTables\Facades\DataTables;

class CollectionController extends Controller
{
    use VerifiesAccessPassword;

    public function index(Request $request)
    {
        if ($request->ajax()) {
            $collections = Collection::with('productSetting')
                ->when($request->filled('product_setting_id'), function ($query) use ($request) {
                    $query->where('product_setting_id', $request->input('product_setting_id'));
                })
                ->when($request->filled('material_type'), function ($query) use ($request) {
                    $query->where('material', $request->input('material_type'));
                })
                ->when($request->filled('edging'), function ($query) use ($request) {
                    $query->where('edging', $request->input('edging'));
                })
                ->when($request->filled('from_date'), function ($query) use ($request) {
                    $query->whereDate('created_at', '>=', $request->input('from_date'));
                })
                ->when($request->filled('to_date'), function ($query) use ($request) {
                    $query->whereDate('created_at', '<=', $request->input('to_date'));
                })
                ->latest();

            return DataTables::of($collections)
                ->addIndexColumn()
                ->addColumn('product_name', function ($row) {
                    return $row->productSetting?->name ?? '-';
                })
                ->addColumn('action', function ($row) {
                    return '
                        <div class="d-flex gap-1">
                            <button class="btn btn-sm btn-primary" onclick="setDataAddOrEditCollectionModal(this)" data-id="' . $row->id . '">Edit</button>
                            <button class="btn btn-sm btn-danger" onclick="deleteCollection(this)" data-id="' . $row->id . '">Delete</button>
                        </div>
                    ';
                })
                ->rawColumns(['action'])
                ->make(true);
        }

        $products = ProductSetting::select('id', 'name')->orderBy('name')->get();

        return view('console.collections.index', compact('products'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'product_setting_id' => 'required|exists:product_settings,id',
            'quantity' => 'required|integer|min:0',
            'material' => 'required|in:Carpet,Rubber',
            'edging' => 'nullable|in:Blue,Grey,Black,Green,White,Red',
            'name' => 'required|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:50',
        ]);

        Collection::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Collection created successfully.',
        ]);
    }

    public function show(Request $request)
    {
        $collection = Collection::find($request->id);

        if (! $collection) {
            return response()->json([
                'success' => false,
                'message' => 'Collection not found.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $collection,
        ]);
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'id' => 'required|exists:collections,id',
            'product_setting_id' => 'required|exists:product_settings,id',
            'quantity' => 'required|integer|min:0',
            'material' => 'required|in:Carpet,Rubber',
            'edging' => 'nullable|in:Blue,Grey,Black,Green,White,Red',
            'name' => 'required|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:50',
        ]);

        $collection = Collection::findOrFail($validated['id']);
        unset($validated['id']);
        $collection->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Collection updated successfully.',
        ]);
    }

    public function destroy(Request $request)
    {
        $request->validate([
            'id' => 'required|exists:collections,id',
            'password' => 'required|string',
        ]);

        if ($denied = $this->denyUnlessAccessPassword($request)) {
            return $denied;
        }

        $collection = Collection::findOrFail($request->id);
        $collection->delete();

        return response()->json([
            'success' => true,
            'message' => 'Collection deleted successfully.',
        ]);
    }
}
