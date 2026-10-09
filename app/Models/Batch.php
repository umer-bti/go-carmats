<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Batch extends Model
{
    protected $guarded = [];

    public function batchOrders()
    {
        return $this->hasMany(BatchOrder::class);
    }

    public function orders()
    {
        return $this->belongsToMany(Order::class, 'batch_orders')
            ->withPivot('sort_order')
            ->orderByRaw('batch_orders.sort_order IS NULL')
            ->orderBy('batch_orders.sort_order')
            ->orderBy('batch_orders.id');
    }
    
    public function batchDesigns()
    {
        return $this->hasMany(BatchDesign::class);
    }
}