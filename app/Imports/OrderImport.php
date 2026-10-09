<?php

namespace App\Imports;

use App\Models\Order;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithStartRow;

class OrderImport implements ToModel, WithStartRow
{
    protected $productCode;
    protected $materialType;

    public function __construct($productCode, $materialType = null)
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
        return new Order([
            'status'              => $row[0],  // Status
            'order_date'          => $row[1],  // Order Date
            'make_model'          => $row[2],  // Make & Model
            'quantity'            => $row[3],  // Quantity
            'edging'              => $row[4],  // Edging
            'recipient_name'      => $row[5],  // Recipient Name
            'address_line_one'    => $row[6],  // Address Line One
            'address_city'        => $row[7],  // Address City
            'address_postcode'    => $row[8],  // Address Postcode
            'order_id'            => $row[9],  // Order ID
            'order_item_id'       => $row[10], // Order Item ID
            'material_type'       => $this->materialType, // Carpet or Rubber
        ]);
    }
}