<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductSetting extends Model
{
    protected $guarded = [];

    protected $casts = [
        'skus' => 'array',
    ];

    public function files()
    {
        return $this->morphOne(File::class, 'fileable');
    }

    public function dxfFile()
    {
        return $this->morphOne(File::class, 'fileable')->where('tag', 'product_dxf');
    }

    public function imageFile()
    {
        return $this->morphOne(File::class, 'fileable')->where('tag', 'product_image');
    }

    public function allFiles()
    {
        return $this->morphMany(File::class, 'fileable');
    }
}
