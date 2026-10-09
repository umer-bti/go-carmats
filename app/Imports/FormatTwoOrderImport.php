<?php

namespace App\Imports;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class FormatTwoOrderImport implements WithMultipleSheets
{
    protected string $productCode;

    public function __construct(string $productCode)
    {
        $this->productCode = $productCode;
    }

    /**
     * Define which sheets to import (by index and by name for robustness)
     */
    public function sheets(): array
    {
        return [
            'Carpet' => new FormatTwoSheetImport($this->productCode, 'Carpet'),
            'Rubber' => new FormatTwoSheetImport($this->productCode, 'Rubber'),
        ];
    }
} 