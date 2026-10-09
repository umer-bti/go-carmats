<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Evri shipping label – 6 fields from DB.
 * 1. api_key (Username), 2. api_secret (Password) – Basic auth
 * 3. client_id, 4. client_name, 5. child_client_id, 6. child_client_name – XML request
 */
class EvriSetting extends Model
{
    protected $table = 'evri_settings';

    protected $fillable = [
        'api_key',
        'api_secret',
        'client_id',
        'client_name',
        'child_client_id',
        'child_client_name',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    protected $hidden = [
        'api_key',
        'api_secret',
    ];

    /** The single active account used for label printing. */
    public static function getActive(): ?self
    {
        return static::where('is_active', true)->first();
    }

    /** Set this account as active and all others inactive. */
    public function setActive(): void
    {
        static::query()->update(['is_active' => false]);
        $this->update(['is_active' => true]);
    }

    /** Single row (backward compat). */
    public static function get(): ?self
    {
        return static::first();
    }

    /** Masked username (api_key) for table display. */
    public function getMaskedApiKeyDisplayAttribute(): string
    {
        return $this->api_key ? '••••••••' : '—';
    }

    /** Masked password (api_secret) for table display. */
    public function getMaskedApiSecretDisplayAttribute(): string
    {
        return $this->api_secret ? '••••••••' : '—';
    }
}
