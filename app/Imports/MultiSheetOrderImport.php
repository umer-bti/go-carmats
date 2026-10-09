<?php

namespace App\Imports;

use App\Models\Order;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithConditionalSheets;

class MultiSheetOrderImport implements WithMultipleSheets
{
    use WithConditionalSheets;
    
    protected $productCode;

    public function __construct($productCode)
    {
        $this->productCode = $productCode;
    }

    /**
     * Define which sheets to import
     */
    public function sheets(): array
    {
        return [
            0 => new SheetOrderImport($this->productCode, 'Carpet'),
            1 => new SheetOrderImport($this->productCode, 'Rubber'),
        ];
    }
    
    /**
     * Conditionally apply imports
     */
    public function conditionalSheets(): array
    {
        return [
            0 => true, // Always import first sheet (Carpet)
            1 => true, // Always try to import second sheet (Rubber)
        ];
    }
}