<?php

/**
 * Test script for DXF conversion integration
 * Run this script to test if the Python API is working correctly
 */

require_once 'vendor/autoload.php';

use App\Services\DxfConversionService;
use Illuminate\Http\UploadedFile;

// Simple test class to simulate Laravel environment
class DxfConversionTest
{
    private $conversionService;

    public function __construct()
    {
        $this->conversionService = new DxfConversionService();
    }

    public function testConnection()
    {
        echo "🔍 Testing connection to Python API...\n";
        
        if ($this->conversionService->testConnection()) {
            echo "✅ Connection successful!\n";
            return true;
        } else {
            echo "❌ Connection failed!\n";
            return false;
        }
    }

    public function testConversion($dxfFilePath)
    {
        if (!file_exists($dxfFilePath)) {
            echo "❌ DXF file not found: $dxfFilePath\n";
            return false;
        }

        echo "🔄 Testing DXF conversion...\n";
        
        // Create a mock UploadedFile
        $uploadedFile = new UploadedFile(
            $dxfFilePath,
            basename($dxfFilePath),
            'application/octet-stream',
            null,
            true
        );

        $result = $this->conversionService->convertDxfToImage($uploadedFile, 'Test Product');

        if ($result['success']) {
            echo "✅ Conversion successful!\n";
            echo "📁 Image saved to: " . $result['image_path'] . "\n";
            return true;
        } else {
            echo "❌ Conversion failed: " . $result['error'] . "\n";
            return false;
        }
    }

    public function runAllTests()
    {
        echo "🚀 Starting DXF Conversion Tests\n";
        echo "================================\n\n";

        // Test 1: Connection
        $connectionOk = $this->testConnection();
        echo "\n";

        if (!$connectionOk) {
            echo "❌ Cannot proceed with conversion test - Python API is not available\n";
            echo "💡 Make sure to start the Python service first:\n";
            echo "   cd 'fastApiProject - python'\n";
            echo "   python start_server.py\n";
            return;
        }

        // Test 2: Conversion (if we have a test DXF file)
        $testDxfFile = 'fastApiProject - python/data/uploads/test.dxf';
        if (file_exists($testDxfFile)) {
            $this->testConversion($testDxfFile);
        } else {
            echo "⚠️  No test DXF file found at: $testDxfFile\n";
            echo "💡 To test conversion, place a DXF file at that location\n";
        }

        echo "\n✨ Tests completed!\n";
    }
}

// Run the tests
$test = new DxfConversionTest();
$test->runAllTests(); 