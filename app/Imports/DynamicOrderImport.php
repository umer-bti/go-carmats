<?php

namespace App\Imports;

use App\Models\Order;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithStartRow;
use Maatwebsite\Excel\Concerns\WithCalculatedFormulas;

class DynamicOrderImport implements ToCollection, WithStartRow, WithCalculatedFormulas
{
    protected $productCode;
    protected $columnMapping;

    public function __construct($productCode)
    {
        $this->productCode = $productCode;
        $this->initializeColumnMapping();
    }

    /**
     * Skip the header row
     */
    public function startRow(): int
    {
        return 2;
    }

    /**
     * Initialize the column mapping for the new sheet format
     */
    private function initializeColumnMapping()
    {
        $this->columnMapping = [
            // Standard columns that will be displayed in the table
            'order_id' => null,
            'order_item_id' => null,
            'postal_code' => null,
            'no_of_clips' => null,
            'product_code' => null,
            'quantity' => null,
            'product' => null,
            'edging' => null,
            'recipient_name' => null,
            'address' => null,
            'city' => null,
            'description' => null,
            'date' => null,
            'status' => null,
            'make_model' => null,
            'color' => null,
            
            // Additional columns that will be stored but not displayed
            'additional_columns' => []
        ];
    }

    /**
     * Process the collection of rows
    */
    public function collection(Collection $collection)
    {
        if ($collection->isEmpty()) {
            Log::info('Import: Empty collection received');
            return;
        }

        // Get headers from first row
        $headers = $collection->first()->toArray();
        Log::info('Import: Headers found', ['headers' => $headers]);
        
        // Map headers to our standard columns
        $this->mapHeadersToColumns($headers);
        Log::info('Import: Column mapping completed', ['mapping' => $this->columnMapping]);
        
        // Debug: Check if make_model column was found
        if ($this->columnMapping['make_model'] === null) {
            Log::warning('Import: make_model column not found! Available columns:', ['available_columns' => array_keys($this->columnMapping['additional_columns'])]);
        }
        
        // Process all rows in a single transaction – any error rolls back the entire import
        $processedCount = 0;
        DB::transaction(function () use ($collection, &$processedCount) {
            foreach ($collection->skip(1) as $row) {
                if ($this->processRow($row->toArray())) {
                    $processedCount++;
                }
            }
        });

        Log::info('Import: Processing completed', ['processed_count' => $processedCount]);
    }

    /**
     * Map Excel headers to our standard columns
     * Since this Excel file doesn't have clear headers, we'll use intelligent detection
     */
    private function mapHeadersToColumns($headers)
    {
        // First, try to map based on header names
        foreach ($headers as $index => $header) {
            $header = strtolower(trim($header));
            
            // Map known columns
            if (str_contains($header, 'order id') || str_contains($header, 'orderid')) {
                $this->columnMapping['order_id'] = $index;
            } elseif (str_contains($header, 'order item') || str_contains($header, 'orderitem')) {
                $this->columnMapping['order_item_id'] = $index;
            } elseif (str_contains($header, 'postal') || str_contains($header, 'zip') || str_contains($header, 'postcode')) {
                $this->columnMapping['postal_code'] = $index;
            } elseif (str_contains($header, 'clips') || str_contains($header, 'clip')) {
                $this->columnMapping['no_of_clips'] = $index;
            } elseif (str_contains($header, 'product code') || str_contains($header, 'productcode')) {
                $this->columnMapping['product_code'] = $index;
            } elseif (str_contains($header, 'quantity') || str_contains($header, 'qty')) {
                $this->columnMapping['quantity'] = $index;
            } elseif (str_contains($header, 'product') || str_contains($header, 'item')) {
                $this->columnMapping['product'] = $index;
            } elseif (str_contains($header, 'edging') || str_contains($header, 'edge')) {
                $this->columnMapping['edging'] = $index;
            } elseif (str_contains($header, 'recipient') || str_contains($header, 'customer') || str_contains($header, 'name')) {
                $this->columnMapping['recipient_name'] = $index;
            } elseif (str_contains($header, 'address') || str_contains($header, 'street')) {
                $this->columnMapping['address'] = $index;
            } elseif (str_contains($header, 'city') || str_contains($header, 'town')) {
                $this->columnMapping['city'] = $index;
            } elseif (str_contains($header, 'description') || str_contains($header, 'desc')) {
                $this->columnMapping['description'] = $index;
            } elseif (str_contains($header, 'date') || str_contains($header, 'order date')) {
                $this->columnMapping['date'] = $index;
            } elseif (str_contains($header, 'status') || str_contains($header, 'state')) {
                $this->columnMapping['status'] = $index;
            } elseif (str_contains($header, 'product-name') || str_contains($header, 'product name') || str_contains($header, 'productname') || str_contains($header, 'product')) {
                $this->columnMapping['make_model'] = $index;
                Log::info('Import: Found make_model column', ['header' => $header, 'index' => $index]);
            } else {
                // Store additional columns for later use
                $this->columnMapping['additional_columns'][$index] = $header;
            }
        }
        
        // If no headers were found, use intelligent column detection based on content
        if ($this->columnMapping['make_model'] === null) {
            $this->intelligentColumnDetection($headers);
        }
    }
    
    /**
     * Intelligent column detection when headers are not clear
     */
    private function intelligentColumnDetection($headers)
    {
        Log::info('Import: Using intelligent column detection');
        
        // Look for the product description column (contains "GCM" and "Edging")
        foreach ($headers as $index => $header) {
            if (is_string($header) && str_contains($header, 'GCM') && str_contains($header, 'Edging')) {
                $this->columnMapping['make_model'] = $index;
                Log::info('Import: Found make_model column by content', ['index' => $index, 'content' => $header]);
                break;
            }
        }
        
        // Look for order ID column (contains hyphens, looks like Amazon order ID)
        foreach ($headers as $index => $header) {
            if (is_string($header) && str_contains($header, '-') && str_contains($header, '0')) {
                $this->columnMapping['order_id'] = $index;
                Log::info('Import: Found order_id column by content', ['index' => $index, 'content' => $header]);
                break;
            }
        }
        
        // Look for order item ID column (numeric, shorter)
        foreach ($headers as $index => $header) {
            if (is_string($header) && strlen($header) < 10 && is_numeric($header) && $index != $this->columnMapping['order_id']) {
                $this->columnMapping['order_item_id'] = $index;
                Log::info('Import: Found order_item_id column by content', ['index' => $index, 'content' => $header]);
                break;
            }
        }
        
        // Look for postal code column (contains letters and numbers, specific format)
        foreach ($headers as $index => $header) {
            if (is_string($header) && preg_match('/^[A-Z]{1,2}[0-9][A-Z0-9]?\s*[0-9][A-Z]{2}$/i', $header)) {
                $this->columnMapping['postal_code'] = $index;
                Log::info('Import: Found postal_code column by content', ['index' => $index, 'content' => $header]);
                break;
            }
        }
        
        // Look for quantity column (single digit)
        foreach ($headers as $index => $header) {
            if (is_numeric($header) && $header > 0 && $header < 100) {
                $this->columnMapping['quantity'] = $index;
                Log::info('Import: Found quantity column by content', ['index' => $index, 'content' => $header]);
                break;
            }
        }
        
        // Look for recipient name column (contains spaces, looks like a name)
        foreach ($headers as $index => $header) {
            if (is_string($header) && str_contains($header, ' ') && strlen($header) > 5 && !str_contains($header, '@')) {
                $this->columnMapping['recipient_name'] = $index;
                Log::info('Import: Found recipient_name column by content', ['index' => $index, 'content' => $header]);
                break;
            }
        }
        
        // Look for address column (contains "road", "street", etc.)
        foreach ($headers as $index => $header) {
            if (is_string($header) && (str_contains(strtolower($header), 'road') || str_contains(strtolower($header), 'street') || str_contains(strtolower($header), 'lane'))) {
                $this->columnMapping['address'] = $index;
                Log::info('Import: Found address column by content', ['index' => $index, 'content' => $header]);
                break;
            }
        }
        
        // Look for city column (looks like a city name, not an order ID or status)
        foreach ($headers as $index => $header) {
            if (is_string($header) && 
                !str_contains($header, ' ') && 
                strlen($header) > 2 && 
                !is_numeric($header) && 
                !str_contains($header, '-') && 
                !str_contains($header, '0') &&
                !str_contains(strtolower($header), 'standard') &&
                !str_contains(strtolower($header), 'pending') &&
                !str_contains(strtolower($header), 'completed') &&
                $index != $this->columnMapping['order_id']) {
                $this->columnMapping['city'] = $index;
                Log::info('Import: Found city column by content', ['index' => $index, 'content' => $header]);
                break;
            }
        }
        
        // Look for date column (contains ISO timestamp format)
        foreach ($headers as $index => $header) {
            if (is_string($header) && 
                str_contains($header, 'T') && 
                str_contains($header, ':') && 
                str_contains($header, '+') &&
                preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\+\d{2}:\d{2}$/', $header)) {
                $this->columnMapping['date'] = $index;
                Log::info('Import: Found date column by content', ['index' => $index, 'content' => $header]);
                break;
            }
        }
    }

    /**
     * Process a single row
     */
    private function processRow($row)
    {
        // Skip empty rows
        if (empty($row[0]) && empty($row[1]) && empty($row[2])) {
            return false;
        }

        $orderId = $this->getColumnValue($row, 'order_id');
        $orderItemId = $this->getColumnValue($row, 'order_item_id');
        
        // Skip if this order already exists
        if ($orderId && $orderItemId) {
            $existingOrder = Order::where('order_id', $orderId)
                ->where('order_item_id', $orderItemId)
                ->first();
                
            if ($existingOrder) {
                return false; // Skip this row if it already exists
            }
        }

        // Get product name and determine material type
        $productName = $this->getColumnValue($row, 'make_model');
        $materialType = $this->determineMaterialType($productName);
        
        // Extract vehicle information and clean product name
        $vehicleInfo = $this->extractVehicleInfo($productName);
        $cleanProductName = $vehicleInfo['clean_name'];
        
        // Extract edging color from product name
        $edgingColor = $this->extractEdgingColor($productName);
        
        Log::info('Import: Product name processing', [
            'original_name' => $productName,
            'clean_name' => $cleanProductName,
            'edging_color' => $edgingColor,
            'material_type' => $materialType
        ]);

        // Prepare standard order data
        $orderData = [
            'order_id' => $orderId,
            'order_item_id' => $orderItemId,
            'postal_code' => $this->getColumnValue($row, 'postal_code'),
            'no_of_clips' => $this->getColumnValue($row, 'no_of_clips'),
            'product_code' => $this->getColumnValue($row, 'product_code'),
            'quantity' => $this->getColumnValue($row, 'quantity'),
            'product' => $this->getColumnValue($row, 'product'),
            'edging' => $edgingColor ?: $this->getColumnValue($row, 'edging'),
            'recipient_name' => $this->getColumnValue($row, 'recipient_name'),
            'address' => $this->getColumnValue($row, 'address'),
            'city' => $this->getColumnValue($row, 'city'),
            'description' => $this->getColumnValue($row, 'description'),
            'date' => $this->normalizeDateValue($this->getColumnValue($row, 'date')),
            'status' => $this->getColumnValue($row, 'status'),
            'make_model' => $cleanProductName,
            'color' => null, // We're not using color field anymore
            'material_type' => $materialType,
        ];

        // Collect additional column data
        $additionalData = [];
        foreach ($this->columnMapping['additional_columns'] as $index => $header) {
            if (isset($row[$index]) && !empty($row[$index])) {
                $additionalData[$header] = $row[$index];
            }
        }

        // Add additional data to order
        if (!empty($additionalData)) {
            $orderData['additional_data'] = json_encode($additionalData);
        }

        // Create the order (exception will roll back the whole import)
        Order::updateOrCreate(
            ['order_id' => $orderId, 'order_item_id' => $orderData['order_item_id']],
            $orderData
        );
        return true;
    }

    /**
     * Extract vehicle information from product name
     * Handles the 5 different formats provided
     */
    private function extractVehicleInfo($productName)
    {
        if (empty($productName)) {
            return ['clean_name' => '', 'vehicle_info' => ''];
        }

        $productName = trim($productName);

        // Handle special "GCM Tailored ... Carpet-Rubber" format
        if (preg_match(
            '/\bGCM\s+Tailored\s+.+?\s+Carpet-Rubber\s+Car Mats for\s+(?P<veh>.+?)\s*\([^,]+Edging,\s*(?:Carpet|Rubber)\)/i',
            $productName,
            $m
        )) {
            return [
                'clean_name'   => trim($m['veh']),
                'vehicle_info' => trim($m['veh']),
            ];
        }

        // Your existing generic GCM matcher
        if (preg_match(
            '/\bGCM\s*-\s*(?:Car|Van)?\s*Floor Mats for\s+(?P<veh>.+?(?:Full\s+(?:Floor|Coverage(?:\s+Floor)?)\s+Protection)?)(?=\s+-\s+|-\s*Anti Slip|$)/i',
            $productName,
            $m
        )) {
            return [
                'clean_name'   => trim($m['veh']),
                'vehicle_info' => trim($m['veh']),
            ];
        }

        // Special case: GCM - <Vehicle> Van Floor Mats <Year> - Full Coverage
        if (preg_match(
            '/\bGCM\s*-\s*(?P<veh>.+?\sVan\sFloor Mats\s+\d{4}\+?)\s*-\s*Full\s+Coverage\b/i',
            $productName,
            $m
        )) {
            return [
                'clean_name'   => trim($m['veh']) . ' - Full Coverage',
                'vehicle_info' => trim($m['veh']) . ' - Full Coverage',
            ];
        }


        
        // If no pattern matches, return the original name
        return [
            'clean_name' => $productName,
            'vehicle_info' => $productName
        ];
    }

    /**
     * Extract edging color from product name
     * Looks for the word before "Edging,"
     */
    private function extractEdgingColor($productName)
    {
        if (empty($productName)) {
            return null;
        }

        $productName = trim($productName);
        
        // Look for pattern: [Color] Edging, [Material]
        if (preg_match('/([A-Za-z]+)\s+Edging,\s*(Carpet|Rubber)/i', $productName, $matches)) {
            return trim($matches[1]);
        }
        
        // Look for pattern: [Color] Edging
        if (preg_match('/([A-Za-z]+)\s+Edging/i', $productName, $matches)) {
            return trim($matches[1]);
        }
        
        return null;
    }

    /**
     * Get value from a specific column
     */
    private function getColumnValue($row, $columnKey)
    {
        $index = $this->columnMapping[$columnKey];
        return $index !== null && isset($row[$index]) ? $row[$index] : null;
    }

    /**
     * Normalize and validate date from Excel.
     * - Excel serial (e.g. 46275) → Y-m-d H:i:s
     * - String date → parsed and formatted.
     * Invalid date or plain text in date column throws so import fails.
     */
    private function normalizeDateValue($value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        $value = is_string($value) ? trim($value) : $value;
        if ($value === null || $value === '') {
            return null;
        }
        // Excel date serial (integer or float)
        if (is_numeric($value)) {
            $serial = (float) $value;
            // Reject unreasonable serials (e.g. 1 = 1900-01-01, 36526 ≈ 2000-01-01, 54788 ≈ 2049)
            if ($serial < 36526 || $serial > 54788) {
                throw new \InvalidArgumentException(
                    'Invalid date in sheet. Date value "' . $value . '" is out of allowed range (year 2000–2049). Please use a valid date.'
                );
            }
            $unixTimestamp = (int) (($serial - 25569) * 86400);
            $date = Carbon::createFromTimestamp($unixTimestamp);
            return $date->format('Y-m-d H:i:s');
        }
        // String: must be a parseable date; plain text is rejected
        try {
            $parsed = Carbon::parse($value);
            $formatted = $parsed->format('Y-m-d H:i:s');
            $year = (int) $parsed->format('Y');
            if ($year < 2000 || $year > 2049) {
                throw new \InvalidArgumentException(
                    'Invalid date in sheet. Date "' . $value . '" has year out of allowed range (2000–2049).'
                );
            }
            return $formatted;
        } catch (\Throwable $e) {
            if ($e instanceof \InvalidArgumentException) {
                throw $e;
            }
            throw new \InvalidArgumentException(
                'Invalid date in sheet. The value "' . (is_string($value) ? $value : (string) $value) . '" is not a valid date. Please use a date (e.g. 2025-01-15) or an Excel date serial.',
                0,
                $e
            );
        }
    }

    /**
     * Determine material type from product name
     * Look for "Carpet" or "Rubber" in the product name
     */
    private function determineMaterialType($productName)
    {
        if (empty($productName)) {
            return 'Carpet'; // Default to Carpet
        }

        $productName = trim($productName);
        
        // Check if product name contains "Carpet" or "Rubber"
        // Look for patterns like "Edging, Carpet" or "Edging, Rubber"
        if (preg_match('/Edging,\s*Carpet/i', $productName)) {
            return 'Carpet';
        } elseif (preg_match('/Edging,\s*Rubber/i', $productName)) {
            return 'Rubber';
        }
        
        // Also check for standalone "Carpet" or "Rubber" at the end
        if (preg_match('/Carpet$/i', $productName)) {
            return 'Carpet';
        } elseif (preg_match('/Rubber$/i', $productName)) {
            return 'Rubber';
        }
        
        // If no clear indicator, default to Carpet
        return 'Carpet';
    }
}
