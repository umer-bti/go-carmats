<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;

/**
 * Format 2 import template: two sheets (Carpet, Rubber), headers only, no orders.
 */
class FormatTwoTemplateExport implements WithMultipleSheets
{
    public function sheets(): array
    {
        return [
            new FormatTwoTemplateSheet('Carpet'),
            new FormatTwoTemplateSheet('Rubber'),
        ];
    }
}
