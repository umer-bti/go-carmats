<?php

namespace App\Exports;

use Illuminate\Contracts\Support\Responsable;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class TrackingExport implements FromCollection, WithHeadings, WithMapping, Responsable
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
            'Order ID', 'Material Type', 'Make & Model', 'Quantity', 'Recipient', 'Address', 'City', 'Postcode', 'Order Date', 'Tracking Number'
        ];
    }

    public function map($o): array
    {
        return [
            $o->order_id,
            $o->material_type,
            $o->make_model,
            $o->quantity,
            $o->recipient_name,
            $o->address,
            $o->city,
            $o->postal_code,
            $o->date,
            $o->tracking_number,
        ];
    }
}


