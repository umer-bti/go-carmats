<?php

namespace App\Models;

use App\Models\Prestock;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Order extends Model
{
    use SoftDeletes;
    
    protected $guarded = [];

    protected $casts = [
        'additional_data' => 'array',
        'scan_time' => 'datetime',
    ];

    public function getDateAttribute($value)
    {
        return Carbon::parse($value)->format('d-m-Y H:i:s');
    }

    public function batchOrders()
    {
        return $this->hasMany(BatchOrder::class);
    }

    // An order belongs to many batches through batch orders
    public function batches()
    {
        return $this->belongsToMany(Batch::class, 'batch_orders');
    }

    /**
     * Get additional data for display (if needed)
     */
    public function getAdditionalData($key = null)
    {
        if ($key) {
            return $this->additional_data[$key] ?? null;
        }
        return $this->additional_data;
    }
    public function files()
    {
        return $this->morphOne(File::class, 'fileable')->where('tag', 'labels');
    }

    public function hasSavedLabel(): bool
    {
        $this->loadMissing('files');

        return $this->files !== null;
    }

    public function savedLabelUrl(): ?string
    {
        $this->loadMissing('files');

        if (! $this->files?->path) {
            return null;
        }

        return asset('storage/' . $this->files->path);
    }

    public function recordLabelPrinted(): int
    {
        $count = (int) ($this->label_count ?? 0) + 1;
        $this->update(['label_count' => $count]);

        return $count;
    }

    public function stitcher()
    {
        return $this->belongsTo(Stitcher::class);
    }

    public function amazonReturn()
    {
        return $this->belongsTo(AmazonReturn::class, 'amazon_return_id');
    }

    public function prestock()
    {
        return $this->belongsTo(Prestock::class, 'prestock_id');
    }

    public function evriShipmentVerification()
    {
        return $this->hasOne(EvriShipmentVerification::class);
    }

    public const SHIPPING_PROVIDER_EVRI = 'evri';

    public const SHIPPING_PROVIDER_VEEQO = 'veeqo';

    public const SHIPPING_PROVIDER_SHIPMATE = 'shipmate';

    /**
     * @return array<string, string>
     */
    public static function shippingProviderLabels(): array
    {
        return [
            self::SHIPPING_PROVIDER_EVRI => 'Evri',
            self::SHIPPING_PROVIDER_VEEQO => 'Veeqo',
            self::SHIPPING_PROVIDER_SHIPMATE => 'Parcelforce',
        ];
    }

    public function scopeScanned($query)
    {
        return $query->whereNotNull('scan_time');
    }

    public function scopeGeneratedByProvider($query, string $provider)
    {
        return $query->where('generated_by', $provider);
    }

    public static function scannedShipmentCountByProvider(string $provider, ?Carbon $start = null, ?Carbon $end = null): int
    {
        $query = static::query()
            ->scanned()
            ->generatedByProvider($provider);

        if ($start !== null && $end !== null) {
            $query->whereBetween('scan_time', [$start, $end]);
        }

        return $query->count();
    }

    public static function additionalLabelCountByProvider(string $provider, ?Carbon $start = null, ?Carbon $end = null): int
    {
        $query = static::query()
            ->scanned()
            ->generatedByProvider($provider);

        if ($start !== null && $end !== null) {
            $query->whereBetween('scan_time', [$start, $end]);
        }

        return (int) $query->sum('label_count');
    }

    /**
     * @return list<array{key: string, name: string, count: int, additional_count: int}>
     */
    public static function shippingProviderScanCounts(?Carbon $start = null, ?Carbon $end = null): array
    {
        return collect(static::shippingProviderLabels())
            ->map(fn (string $name, string $key) => [
                'key' => $key,
                'name' => $name,
                'count' => static::scannedShipmentCountByProvider($key, $start, $end),
                'additional_count' => static::additionalLabelCountByProvider($key, $start, $end),
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array{
     *     date: string,
     *     date_label: string,
     *     evri: int,
     *     veeqo: int,
     *     shipmate: int,
     *     total: int
     * }>
     */
    public static function dailyGroupedShippingProviderScanRows(Carbon $start, Carbon $end): array
    {
        $providerKeys = array_keys(static::shippingProviderLabels());

        $dailyCounts = static::query()
            ->scanned()
            ->whereIn('generated_by', $providerKeys)
            ->whereBetween('scan_time', [$start, $end])
            ->selectRaw('DATE(scan_time) as scan_date, generated_by, COUNT(*) as scan_count, SUM(IFNULL(label_count, 0)) as additional_count')
            ->groupBy('scan_date', 'generated_by')
            ->get();

        $countsByDate = [];
        foreach ($dailyCounts as $row) {
            $countsByDate[$row->scan_date][$row->generated_by] = [
                'scan_count' => (int) $row->scan_count,
                'additional_count' => (int) $row->additional_count,
            ];
        }

        $rows = [];
        $current = $end->copy()->startOfDay();
        $rangeStart = $start->copy()->startOfDay();

        while ($current->gte($rangeStart)) {
            $dateKey = $current->format('Y-m-d');
            $day = [
                'date' => $dateKey,
                'date_label' => $current->format('d-m-Y'),
                'evri' => 0,
                'evri_additional' => 0,
                'veeqo' => 0,
                'veeqo_additional' => 0,
                'shipmate' => 0,
                'shipmate_additional' => 0,
                'total' => 0,
                'total_additional' => 0,
            ];

            foreach ($providerKeys as $key) {
                $count = $countsByDate[$dateKey][$key]['scan_count'] ?? 0;
                $additional = $countsByDate[$dateKey][$key]['additional_count'] ?? 0;
                
                $day[$key] = $count;
                $day[$key . '_additional'] = $additional;
                
                $day['total'] += $count;
                $day['total_additional'] += $additional;
            }

            $rows[] = $day;
            $current->subDay();
        }

        return $rows;
    }
}