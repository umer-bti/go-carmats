<?php

namespace App\Exports;

use App\Models\ProductSetting;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class CustomOrdersExport implements FromCollection, WithHeadings, WithMapping
{
    protected Builder $query;


    public function __construct(Builder $query)
    {
        $this->query = $query;

    }

    public function collection()
    {
        return $this->query->get();
    }

    public function headings(): array
    {
        return [
            'Status',
            'Order Date',
            'Product Code',
            'Make & Model',
            'Quantity',
            'Edging',
            'Recipient Name',
            'Address Line One',
            'Address City',
            'Address Postcode',
            'Order ID',
            'Order Item ID'

        ];
    }

    public function map($order): array
    {
        // Build Make & Model field with format: Edging - Product name - 450/3MM
        $makeModel = $order->edging . ' - ' . $order->make_model;

        // Add 450 if carpet, 3MM if rubber
        if (strtolower($order->material_type) === 'carpet') {
            $makeModel .= ' - 450';
        } elseif (strtolower($order->material_type) === 'rubber') {
            $makeModel .= ' - 3MM';
        }
        $productSetting = ProductSetting::whereJsonContains('skus',$order->sku)->first();

        return [
            $order->status ,
            $order->date,
            $productSetting->code ?? '',
            $makeModel,
            $order->quantity,
            $order->edging,
            $order->recipient_name,
            $order->address,
            $order->city,
            $order->postal_code,
            $order->order_id,
            $order->order_item_id,

        ];
    }
}

