<?php

namespace App\Http\Controllers\Console\Product;

use App\Http\Controllers\Controller;
use App\Http\Concerns\VerifiesAccessPassword;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Order;
use App\Models\ProductSetting;
use Yajra\DataTables\Facades\DataTables;
use App\Services\FileService;
use App\Services\DxfConversionService;
use App\Rules\DxfFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class ProductController extends Controller
{
    use VerifiesAccessPassword;

    protected $dxfConversionService;

    public function __construct(DxfConversionService $dxfConversionService)
    {
        $this->dxfConversionService = $dxfConversionService;
    }

    public function index(Request $request)
    {
        if ($request->ajax()) {
            $productSettings = ProductSetting::query();

            return DataTables::of($productSettings)
                ->addIndexColumn()
                ->addColumn('checkbox', function ($row) {
                    return '<input type="checkbox" class="product-checkbox" value="' . $row->id . '" data-id="' . $row->id . '">';
                })
                ->addColumn('image', function ($row) {
                    // Try to get the converted image first, fallback to DXF file or default image
                    $imageUrl = $row->imageFile?->path
                        ? asset('storage/' . $row->imageFile->path)
                        : ($row->dxfFile?->path
                            ? asset('storage/' . $row->dxfFile->path)
                            : asset('themes/console/assets/img/pages/mat.jpg'));

                    return '<img src="' . $imageUrl . '" loading="lazy" width="50" height="50" alt="Image">';
                })
                ->addColumn('action', function ($row) {
                    return '
                        <div class="d-flex gap-1">
                            <button class="btn btn-sm btn-primary" onclick="setDataAddOrEditProductFileModal(this)" data-id="' . $row->id . '">Edit</button>
                            <button class="btn btn-sm btn-danger" onclick="deleteProduct(this)" data-id="' . $row->id . '">Delete</button>
                        </div>
                    ';
                })
                ->rawColumns(['checkbox', 'image', 'action'])
                ->make(true);
        } else {
            $products = Order::pluck('make_model')->unique()->sort()->values();


            return view('console.products.index', compact('products'));
        }
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'nullable|string|max:255',
            'skus' => 'required|array|min:1',
            'skus.*' => 'required|string|max:255',
            'no_of_clips' => 'nullable|string',
            'no_of_mats'=> 'nullable|integer',
            'description' => 'nullable|string|max:255',
            'file' => ['required', 'file', new DxfFile],
        ]);

        $inputName = trim(strtolower($request->name));
        
        // Check if a product with the same name already exists (case-insensitive)
        $existingProduct = ProductSetting::whereRaw('LOWER(TRIM(name)) = ?', [$inputName])->first();

        
        $file = $request->file('file');
        if ($file){
            // Convert DXF to image using Python service
            $conversionResult = $this->dxfConversionService->convertDxfToImage($file, $request->input('name'));

            if (!$conversionResult['success']) {
                return response()->json([
                    'success' => false,
                    'message' => 'File conversion failed: ' . $conversionResult['error'],
                ], 400);
            }
        }


        // Save product within a transaction to prevent race conditions
        try {
            $product = DB::transaction(function () use ($request) {
                
                return ProductSetting::create([
                    'name' => $request->input('name'),
                    'code' => $request->input('code') ?: null,
                    'skus' => $request->input('skus') ?: null,
                    'no_of_clips' => $request->input('no_of_clips') ?: null,
                    'description' => $request->input('description') ?: null,
                    'no_of_mats' => $request->input('no_of_mats') ?: null,
                ]);
            });
        }  catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }

        if ($file){
            // Save the uploaded DXF file
            (new FileService)->createOrUpdate(
                $file,
                $product,
                'product_dxf',
                true
            );

            // Save the converted image file
            $imageFile = $this->createImageFileFromPath($conversionResult['image_path'], $conversionResult['image_filename']);
            (new FileService)->createOrUpdate(
                $imageFile,
                $product,
                'product_image',
                true
            );
        }


        return response()->json([
            'success' => true,
            'message' => 'Product file uploaded and converted successfully.',
        ]);
    }

    public function show(Request $request)
    {
        $product = ProductSetting::find($request->id);

        if (!$product) {
            return response()->json([
                'success' => false,
                'message' => 'Product not found.'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $product
        ]);
    }

    public function update(Request $request)
    {
        $request->validate([
            'id' => 'required|exists:product_settings,id',
            'name' => 'required|string|max:255',
            'skus' => 'required|array|min:1',
            'skus.*' => 'required|string|max:255',
            'no_of_clips' => 'nullable|string',
            'no_of_mats'=> 'nullable|integer',
            'description' => 'nullable|string|max:255',
            'code' => 'nullable|string|max:255',
            'file' => ['nullable', 'file', new DxfFile],
        ]);

        $product = ProductSetting::findOrFail($request->id);
        $file = $request->file('file');
        if ($file) {

            // Convert DXF to image using Python service
            $conversionResult = $this->dxfConversionService->convertDxfToImage($file, $request->input('name'));

            if (!$conversionResult['success']) {
                return response()->json([
                    'success' => false,
                    'message' => 'File conversion failed: ' . $conversionResult['error'],
                ], 400);
            }
        }

        // Update product settings
        try {
            $product->update([
                'name' => $request->input('name'),
                'code' => $request->input('code') ?: null,
                'skus' => $request->input('skus') ?: null,
                'no_of_clips' => $request->input('no_of_clips') ?: null,
                'description' => $request->input('description') ?: null,
                'no_of_mats' => $request->input('no_of_mats') ?: null,
            ]);
        } catch (\Illuminate\Database\QueryException $e) {
            // Handle duplicate key error
            if ($e->getCode() == 23000) { // MySQL duplicate entry error
                return response()->json([
                    'success' => false,
                    'message' => 'A product with this name already exists.',
                ], 422);
            }
            throw $e; // Re-throw other database errors
        }

        if ($file) {
            // Save the uploaded DXF file
            (new FileService)->createOrUpdate(
                $file,
                $product,
                'product_dxf',
                true
            );

            // Save the converted image file
            $imageFile = $this->createImageFileFromPath($conversionResult['image_path'], $conversionResult['image_filename']);
            (new FileService)->createOrUpdate(
                $imageFile,
                $product,
                'product_image',
                true
            );
        }

        return response()->json([
            'success' => true,
            'message' => 'Product file updated and converted successfully.'
        ]);
    }

    public function destroy(Request $request)
    {
        $request->validate([
            'id' => 'required|exists:product_settings,id',
            'password' => 'required|string',
        ]);

        if ($denied = $this->denyUnlessAccessPassword($request)) {
            return $denied;
        }

        $product = ProductSetting::findOrFail($request->id);

        (new FileService)->delete($product);

        $product->delete();

        return response()->json([
            'success' => true,
            'message' => 'Product deleted successfully.',
        ]);
    }

    public function getProductImage(Request $request)
    {
        $request->validate([
            'product_sku' => 'required|string|max:255',
        ]);

        $productName = $request->product_sku;
        
        // Find the product setting based on product name (case-insensitive)
        $productSetting = ProductSetting::whereJsonContains('skus',$productName)->first();
 
        
        if ($productSetting) {
            // Try to get the converted image first, fallback to DXF file or default image
            $imageUrl = $productSetting->imageFile?->path
                ? asset('storage/' . $productSetting->imageFile->path)
                : ($productSetting->dxfFile?->path
                    ? asset('storage/' . $productSetting->dxfFile->path)
                    : asset('themes/console/assets/img/pages/mat.jpg'));
            
            return response()->json([
                'success' => true,
                'image_url' => $imageUrl,
            ]);
        } else {
            return response()->json([
                'success' => true,
                'image_url' => asset('themes/console/assets/img/pages/mat.jpg'),
            ]);
        }
    }

    public function getSkusByProductName(Request $request)
    {
        $request->validate([
            'product_name' => 'required|string|max:255',
        ]);

        $productName = trim($request->product_name);
        
        // Get unique SKUs from orders where make_model matches the product name
        $skus = Order::where('make_model', $productName)
            ->whereNotNull('sku')
            ->pluck('sku')
            ->unique()
            ->filter()
            ->values();

        return response()->json([
            'success' => true,
            'skus' => $skus,
        ]);
    }

    /**
     * Create an UploadedFile instance from a file path
     *
     * @param string $filePath
     * @param string $filename
     * @return \Illuminate\Http\UploadedFile
     */
    private function createImageFileFromPath(string $filePath, string $filename): \Illuminate\Http\UploadedFile
    {
        $fullPath = storage_path('app/public/' . $filePath);
        
        if (!file_exists($fullPath)) {
            throw new \Exception('Image file not found: ' . $fullPath);
        }

        return new \Illuminate\Http\UploadedFile(
            $fullPath,
            $filename,
            'image/jpeg',
            null,
            true
        );
    }

    public function exportDxfFiles(Request $request)
    {

        $request->validate([
            'product_ids' => 'required|array|min:1',
            'product_ids.*' => 'required|exists:product_settings,id',
        ]);

        $productIds = $request->input('product_ids');
        
        // Get products with their DXF files
        $products = ProductSetting::with('dxfFile')
            ->whereIn('id', $productIds)
            ->get();

        // Check if any products have DXF files
        $productsWithDxf = $products->filter(function ($product) {
            return $product->dxfFile && $product->dxfFile->path;
        });

        if ($productsWithDxf->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'No DXF files found for the selected products.',
            ], 404);
        }

        // Create a temporary ZIP file
        $zipFileName = 'product_dxf_files_' . date('Y-m-d_His') . '.zip';
        $zipPath = storage_path('app/temp/' . $zipFileName);

        // Ensure temp directory exists
        if (!file_exists(storage_path('app/temp'))) {
            mkdir(storage_path('app/temp'), 0755, true);
        }

        $zip = new \ZipArchive();
        
        if ($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create ZIP file.',
            ], 500);
        }

        $filesAdded = 0;
        foreach ($productsWithDxf as $product) {
            $dxfFile = $product->dxfFile;
            $filePath = storage_path('app/public/' . $dxfFile->path);
            
            if (file_exists($filePath)) {
                // Sanitize product name for filename
                $sanitizedName = preg_replace('/[^A-Za-z0-9_\-]/', '_', $product->name);
                $extension = pathinfo($dxfFile->path, PATHINFO_EXTENSION);
                $fileName = $sanitizedName . '.' . $extension;
                
                // Add file to ZIP
                $zip->addFile($filePath, $fileName);
                $filesAdded++;
            }
        }

        $zip->close();

        if ($filesAdded === 0) {
            // Clean up empty ZIP file
            if (file_exists($zipPath)) {
                unlink($zipPath);
            }
            
            return response()->json([
                'success' => false,
                'message' => 'No DXF files could be added to the archive.',
            ], 404);
        }

        // Return the ZIP file for download and delete after sending
        return response()->download($zipPath, $zipFileName)->deleteFileAfterSend(true);
    }


}
