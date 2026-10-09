<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class AmazonReturn extends Model
{
    protected $guarded = [];

    protected $casts = [
        'return_request_date' => 'datetime',
        'received' => 'boolean',
    ];

    public function relatedOrder()
    {
        return $this->hasOne(Order::class, 'amazon_return_id');
    }

    public function getReturnRequestDateAttribute($value)
    {
        return Carbon::parse($value)->format('d-m-Y H:i:s');
    }
}
