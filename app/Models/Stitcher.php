<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Stitcher extends Model
{
    protected $fillable = [
        'name',
    ];

    public function orders()
    {
        return $this->hasMany(Order::class);
    }
}
