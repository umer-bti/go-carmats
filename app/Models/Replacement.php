<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Replacement extends Model
{
    use SoftDeletes;

    protected $guarded = [];

    protected $casts = [
        'selected_items' => 'array',
        'is_printed'     => 'boolean',
        'printed_at'     => 'datetime',
    ];

}
