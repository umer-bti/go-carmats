<?php

namespace App\Imports;

use App\Models\Order;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithStartRow;
use Maatwebsite\Excel\Concerns\WithCalculatedFormulas;
use Illuminate\Support\Facades\Log;

class SheetOrderImport implements ToModel, WithStartRow, WithCalculatedFormulas
{
    protected $productCode;
    protected $materialType;

    public function __construct($productCode, $materialType)
    {
        $this->productCode = $productCode;
        $this->materialType = $materialType;
    }

    /**
     * Skip the header row
     */
    public function startRow(): int
    {
        return 2;
    }

    /**
     * Map the row to the Order model
     */
    public function model(array $row)
    {
        // Skip empty rows
        if (empty($row[0]) && empty($row[1]) && empty($row[2])) {
            return null;
        }
        
        $orderId = $row[9] ?? null;  // Order ID
        $orderItemId = $row[10] ?? null; // Order Item ID
        
        // Skip if this order already exists with the same material type
        // This prevents duplicate imports
        if ($orderId && $orderItemId) {
            $existingOrder = Order::where('order_id', $orderId)
                ->where('order_item_id', $orderItemId)
                ->where('material_type', $this->materialType)
                ->first();
                
            if ($existingOrder) {
                return null; // Skip this row if it already exists
            }
        }
        
        // Process Make & Model to extract color
        $makeModel = $row[2] ?? '';
        $color = null;
        
        // Check if the Make & Model contains a hyphen (indicating color)
        if (strpos($makeModel, '-') !== false) {
            // Split by the first hyphen
            $parts = explode('-', $makeModel, 2);
            $color = trim($parts[0]); // Color is before the hyphen
            $makeModel = trim($parts[1]); // Make & Model is after the hyphen
        }
        
        return new Order([
            'status'              => $row[0] ?? null,  // Status
            'order_date'          => $row[1] ?? null,  // Order Date
            'make_model'          => $makeModel,       // Make & Model (without color)
            'color'               => $color,           // Extracted color
            'quantity'            => $row[3] ?? null,  // Quantity
            'edging'              => $row[4] ?? null,  // Edging
            'recipient_name'      => $row[5] ?? null,  // Recipient Name
            'address_line_one'    => $row[6] ?? null,  // Address Line One
            'address_city'        => $row[7] ?? null,  // Address City
            'address_postcode'    => $row[8] ?? null,  // Address Postcode
            'order_id'            => $orderId,         // Order ID
            'order_item_id'       => $orderItemId,     // Order Item ID
            'material_type'       => $this->materialType, // Carpet or Rubber
        ]);
    }
}