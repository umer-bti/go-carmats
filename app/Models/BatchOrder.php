<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BatchOrder extends Model
{
    protected $guarded = [];

    // A batch order belongs to a batch
    public function batch()
    {
        return $this->belongsTo(Batch::class);
    }

    // A batch order belongs to an order
    public function order()
    {
        return $this->belongsTo(Order::class);
    }
}