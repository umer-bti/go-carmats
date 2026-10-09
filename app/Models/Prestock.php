<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Prestock extends Model
{
    protected $guarded = [];

    protected $casts = [
        'stock' => 'integer',
    ];

    public function productSetting(): BelongsTo
    {
        return $this->belongsTo(ProductSetting::class);
    }
}
