<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

/**
 * Single sheet for Format 2 template (Carpet or Rubber).
 * Headers match FormatTwoSheetImport validation; no data rows.
 * This class is the single source of truth for Format 2 column names and order.
 */
class FormatTwoTemplateSheet implements FromCollection, WithHeadings, WithTitle
{
    protected string $title;

    public function __construct(string $title)
    {
        $this->title = $title;
    }

    public function collection(): Collection
    {
        return collect([]);
    }

    /**
     * Canonical list of column headers for Format 2 (export template & import validation).
     * Import validation checks file headers against this exact pattern.
     */
    public static function getHeadings(): array
    {
        return [
            'Status',
            'Date',
            'Make & Model',
            'Quantity',
            'Edging',
            'Recipient Name',
            'Address',
            'City',
            'Postal Code',
            'Order ID',
            'Order Item ID',
            'Product',
        ];
    }

    public function headings(): array
    {
        return self::getHeadings();
    }

    public function title(): string
    {
        return $this->title;
    }
}
