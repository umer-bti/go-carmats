<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use App\Rules\DxfFile;

class DxfConversionService
{
    protected $pythonApiUrl;
    protected $uploadDir;
    protected $convertedDir;

    public function __construct()
    {
        $this->pythonApiUrl = config('python-api.base_url', 'http://localhost:8000');
        $this->uploadDir = config('python-api.upload_dir', 'dxf_uploads');
        $this->convertedDir = config('python-api.converted_dir', 'dxf_converted');
    }

    /**
     * Convert DXF file to image using Python FastAPI service
     *
     * @param UploadedFile $dxfFile
     * @param string $productName
     * @return array|null
     */
    public function convertDxfToImage(UploadedFile $dxfFile, string $productName = '')
    {
        try {
            // Validate file type using custom rule
            $validator = validator(['file' => $dxfFile], ['file' => new DxfFile]);
            if ($validator->fails()) {
                throw new \Exception('Invalid file type. Only DXF files are allowed.');
            }

            // Send file to Python API
            $response = Http::attach(
                'file',
                file_get_contents($dxfFile),
                $dxfFile->getClientOriginalName()
            )->post($this->pythonApiUrl . '/convert');

            if (!$response->successful()) {
                throw new \Exception('Failed to connect to conversion service: ' . $response->status());
            }

            $result = $response->json();

            if (!$result['success']) {
                throw new \Exception('Conversion failed: ' . ($result['error'] ?? 'Unknown error'));
            }

            // Fix the image URL to use the correct port
            $imageUrl = str_replace('http://localhost:5000', $this->pythonApiUrl, $result['image_url']);
            
            // Download the converted image
            $imageResponse = Http::get($imageUrl);
            
            if (!$imageResponse->successful()) {
                throw new \Exception('Failed to download converted image');
            }

            // Generate unique filename for the image
            $imageFilename = time() . '_' . Str::uuid() . '.jpg';
            $imagePath = $this->convertedDir . '/' . $imageFilename;

            // Save the converted image to storage
            Storage::disk('public')->put($imagePath, $imageResponse->body());

            return [
                'success' => true,
                'image_path' => $imagePath,
                'image_filename' => $imageFilename,
                'original_dxf_name' => $dxfFile->getClientOriginalName()
            ];

        } catch (\Exception $e) {
            Log::error('DXF conversion failed: ' . $e->getMessage(), [
                'file' => $dxfFile->getClientOriginalName(),
                'product' => $productName
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }



    /**
     * Test the connection to the Python API
     *
     * @return bool
     */
    public function testConnection(): bool
    {
        try {
            $response = Http::timeout(5)->get($this->pythonApiUrl . '/');
            return $response->successful();
        } catch (\Exception $e) {
            Log::error('Python API connection test failed: ' . $e->getMessage());
            return false;
        }
    }
} 