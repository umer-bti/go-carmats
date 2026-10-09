<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EvriShipmentVerification extends Model
{
    protected $fillable = [
        'order_id',
        'system_status',
        'system_shipped',
        'system_has_label',
        'system_tracking_number',
        'evri_label_exists',
        'evri_status',
        'evri_status_detail',
        'verification_result',
        'evri_verified_at',
        'evri_error',
    ];

    protected $casts = [
        'system_shipped' => 'boolean',
        'system_has_label' => 'boolean',
        'evri_label_exists' => 'boolean',
        'evri_verified_at' => 'datetime',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
