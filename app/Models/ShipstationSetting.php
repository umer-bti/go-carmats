<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * ShipStation API credentials (API Key + API Secret).
 */
class ShipstationSetting extends Model
{
    protected $table = 'shipstation_settings';

    protected $fillable = [
        'name',
        'client_id',
        'client_secret',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    protected $hidden = [
        'client_secret',
    ];

    public static function activeConfiguredQuery()
    {
        return static::query()
            ->where('is_active', true)
            ->whereNotNull('client_id')
            ->whereNotNull('client_secret')
            ->where('client_id', '!=', '')
            ->where('client_secret', '!=', '');
    }

    public static function getActive(): ?self
    {
        return static::activeConfiguredQuery()->first();
    }

    /** @return \Illuminate\Database\Eloquent\Collection<int, self> */
    public static function getActives()
    {
        return static::activeConfiguredQuery()->get();
    }
}
