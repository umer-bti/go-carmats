<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BatchDesign extends Model
{
    protected $guarded = [];

    public function batch()
    {
        return $this->belongsTo(Batch::class);
    }
}
