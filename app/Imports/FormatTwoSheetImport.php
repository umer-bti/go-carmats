<?php

namespace App\Imports;

use App\Exports\FormatTwoTemplateSheet;
use App\Models\Order;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithStartRow;
use Maatwebsite\Excel\Concerns\WithCalculatedFormulas;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\BeforeSheet;

class FormatTwoSheetImport implements ToModel, WithStartRow, WithCalculatedFormulas, WithEvents
{
    protected string $productCode;
    protected string $materialType;

    public function __construct(string $productCode, string $materialType)
    {
        $this->productCode = $productCode;
        $this->materialType = $materialType;
    }

    public function registerEvents(): array
    {
        return [
            BeforeSheet::class => function (BeforeSheet $event) {
                $this->validateHeaderRow($event);
            },
        ];
    }

    /**
     * Validate that the first row matches the Format 2 template (same as exported empty template).
     * Uses FormatTwoTemplateSheet::getHeadings(). Last column (Product) is optional.
     */
    protected function validateHeaderRow(BeforeSheet $event): void
    {
        $sheet = $event->getSheet()->getDelegate();
        $expected = FormatTwoTemplateSheet::getHeadings();
        $columnCount = count($expected);
        $lastCol = $columnCount <= 26 ? chr(64 + $columnCount) : 'L';
        $headerRow = $sheet->rangeToArray('A1:' . $lastCol . '1', null, true, true, false);
        $actualHeaders = $headerRow[0] ?? [];

        $requiredCount = $columnCount - 1; // Product (last column) is optional

        for ($index = 0; $index < $columnCount; $index++) {
            $expectedName = $expected[$index];
            $actualName = isset($actualHeaders[$index]) ? trim((string) $actualHeaders[$index]) : null;

            $isLastColumn = ($index === $columnCount - 1); // Product is optional

            if ($isLastColumn) {
                if ($actualName === null || $actualName === '') {
                    continue; // Product column missing is OK
                }
                if (strtolower($actualName) !== strtolower($expectedName)) {
                    throw new \InvalidArgumentException(
                        'Invalid file format. Column ' . ($index + 1) . ' should be "' . $expectedName . '" but found "' . $actualName . '". Column names and order must match the template exactly.'
                    );
                }
                continue;
            }

            if ($actualName === null || $actualName === '') {
                throw new \InvalidArgumentException(
                    'Invalid file format. Column ' . ($index + 1) . ' is missing. Expected header "' . $expectedName . '". Please use the correct template with exact column names and order.'
                );
            }

            if (strtolower($actualName) !== strtolower($expectedName)) {
                throw new \InvalidArgumentException(
                    'Invalid file format. Column ' . ($index + 1) . ' should be "' . $expectedName . '" but found "' . $actualName . '". Column names and order must match the template exactly.'
                );
            }
        }

        if (count($actualHeaders) < $requiredCount) {
            $missingIndex = count($actualHeaders);
            throw new \InvalidArgumentException(
                'Invalid file format. Column ' . ($missingIndex + 1) . ' is missing. Expected header "' . $expected[$missingIndex] . '". Please use the correct template with exact column names and order.'
            );
        }

        if (count($actualHeaders) > $columnCount) {
            throw new \InvalidArgumentException(
                'Invalid file format. Too many columns. Expected at most ' . $columnCount . ' columns. Please use the correct template.'
            );
        }
    }

    public function startRow(): int
    {
        return 2; // skip header
    }

    public function model(array $row)
    {
        // Basic empty row guard
        if (!isset($row[0]) && !isset($row[1]) && !isset($row[2])) {
            return null;
        }

        // Map fixed columns for Format 2 (explicit columns per sheet)
        // Adjust indices here if your sheet columns differ
        $status           = $row[0] ?? null;      // Status
        $date             = $this->normalizeDateValue($row[1] ?? null);  // date (Excel serial or string)
        $makeModel        = preg_replace('/^[^-]*-\s*/', '', $row[2]) ?? null;      // Make & Model (already clean)
        $quantity         = $row[3] ?? null;      // Quantity
        $edging           = $row[4] ?? null;      // Edging
        $recipientName    = $row[5] ?? null;      // Recipient Name
        $address          = $row[6] ?? null;      // Address
        $city             = $row[7] ?? null;      // City
        $postalCode       = $row[8] ?? null;      // Postal Code
        $orderId          = $row[9] ?? null;      // Order ID
        $orderItemId      = $row[10] ?? null;     // Order Item ID
        $product          = $row[11] ?? null;     // Product (if present)
        $productCode      = $this->productCode;   // From filename


        // Upsert by (order_id, order_item_id). Any error rolls back the whole sheet.
        try {
            $order = Order::updateOrCreate(
                [
                    'order_id' => $orderId,
                    'order_item_id' => $orderItemId,
                ],
                [
                    'order_item_id' => $orderItemId,
                    'status'         => $status,
                    'date'           => $date,
                    'make_model'     => $makeModel,
                    'quantity'       => $quantity,
                    'edging'         => $edging,
                    'recipient_name' => $recipientName,
                    'address'        => $address,
                    'city'           => $city,
                    'postal_code'    => $postalCode,
                    'product'        => $product,
                    'product_code'   => $productCode,
                    'material_type'  => $this->materialType,
                ]
            );
            return $order;
        } catch (\Throwable $e) {
            Log::error('FormatTwo import failed', [
                'error' => $e->getMessage(),
                'row'   => $row,
                'material' => $this->materialType,
            ]);
            throw $e;
        }
    }

    /**
     * Normalize and validate date from Excel.
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
            if ($serial < 36526 || $serial > 54788) {
                throw new \InvalidArgumentException(
                    'Invalid date in sheet. Date value "' . $value . '" is out of allowed range (year 2000–2049). Please use a valid date.'
                );
            }
            $unixTimestamp = (int) (($serial - 25569) * 86400);
            $date = Carbon::createFromTimestamp($unixTimestamp);
            return $date->format('Y-m-d H:i:s');
        }
        // String: must be a parseable date
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
} 