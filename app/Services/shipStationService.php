<?php

namespace App\Services;


use App\Models\AmazonReturn;
use App\Models\Order;
use App\Models\Prestock;
use App\Models\ProductSetting;
use App\Models\ShipstationSetting;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class shipStationService
{
    public $client_id, $client_secret, $base_url, $shipstation_setting_id;

    public function __construct()
    {
        $this->base_url = config('shipstation.base_url');
    }

    public function getOrders()
    {
        $runStartedAt = microtime(true);
        $settings = ShipstationSetting::getActives();

        if ($settings->isEmpty()) {
            Log::error('ShipStation API Error: No active configured accounts in shipstation_settings');

            return false;
        }

        $allSuccessful = true;
        $grandTotals = [
            'accounts' => 0,
            'pages' => 0,
            'orders_fetched' => 0,
            'line_items_saved' => 0,
            'fetch_sec' => 0,
            'save_sec' => 0,
        ];

        foreach ($settings as $setting) {
            $accountStartedAt = microtime(true);
            $this->setCredentialsFromSetting($setting);

            $page = 1;
            $modifiedDate = $this->generateModifiedDate();
            $totalPages = 1;
            $accountTotals = [
                'pages' => 0,
                'orders_fetched' => 0,
                'line_items_saved' => 0,
                'fetch_sec' => 0,
                'save_sec' => 0,
            ];

            do {
                $fetchStartedAt = microtime(true);
                $response = Http::withBasicAuth($this->client_id, $this->client_secret)
                    ->get($this->base_url . '/orders', [
                        'modifyDateStart' => $modifiedDate,
                        'page' => $page,
                        'pageSize' => 500,
                    ]);
                $fetchSec = round(microtime(true) - $fetchStartedAt, 3);

                if ($response->successful()) {
                    $data = $response->json();
                    $ordersOnPage = count($data['orders'] ?? []);
                    $saveStartedAt = microtime(true);
                    $lineItemsSaved = 0;

                    foreach ($data['orders'] as $order) {
                        $lineItemsSaved += $this->makeOrderData($order);
                    }

                    $saveSec = round(microtime(true) - $saveStartedAt, 3);
                    $totalPages = $data['pages'];
                    $accountTotals['pages']++;
                    $accountTotals['orders_fetched'] += $ordersOnPage;
                    $accountTotals['line_items_saved'] += $lineItemsSaved;
                    $accountTotals['fetch_sec'] += $fetchSec;
                    $accountTotals['save_sec'] += $saveSec;

                    Log::info('ShipStation sync page completed', [
                        'account' => $setting->name,
                        'shipstation_setting_id' => $setting->id,
                        'page' => $page,
                        'total_pages' => $totalPages,
                        'orders_on_page' => $ordersOnPage,
                        'line_items_saved' => $lineItemsSaved,
                        'fetch_sec' => $fetchSec,
                        'save_sec' => $saveSec,
                        'page_total_sec' => round($fetchSec + $saveSec, 3),
                    ]);

                    $page++;
                } else {
                    Log::error("ShipStation API Error ({$setting->name}): " . $response->body(), [
                        'shipstation_setting_id' => $setting->id,
                        'page' => $page,
                        'fetch_sec' => $fetchSec,
                    ]);
                    $allSuccessful = false;
                    break;
                }

            } while ($page <= $totalPages);

            $accountSec = round(microtime(true) - $accountStartedAt, 3);
            $grandTotals['accounts']++;
            $grandTotals['pages'] += $accountTotals['pages'];
            $grandTotals['orders_fetched'] += $accountTotals['orders_fetched'];
            $grandTotals['line_items_saved'] += $accountTotals['line_items_saved'];
            $grandTotals['fetch_sec'] = round($grandTotals['fetch_sec'] + $accountTotals['fetch_sec'], 3);
            $grandTotals['save_sec'] = round($grandTotals['save_sec'] + $accountTotals['save_sec'], 3);

            Log::info('ShipStation sync account completed', [
                'account' => $setting->name,
                'shipstation_setting_id' => $setting->id,
                'pages' => $accountTotals['pages'],
                'orders_fetched' => $accountTotals['orders_fetched'],
                'line_items_saved' => $accountTotals['line_items_saved'],
                'fetch_sec' => round($accountTotals['fetch_sec'], 3),
                'save_sec' => round($accountTotals['save_sec'], 3),
                'account_total_sec' => $accountSec,
            ]);
        }

        $runSec = round(microtime(true) - $runStartedAt, 3);
        Log::info('ShipStation sync run completed', array_merge($grandTotals, [
            'success' => $allSuccessful,
            'run_total_sec' => $runSec,
        ]));

        return $allSuccessful;
    }

    private function setCredentialsFromSetting(ShipstationSetting $setting): void
    {
        $this->client_id = $setting->client_id;
        $this->client_secret = $setting->client_secret;
        $this->shipstation_setting_id = $setting->id;
    }

    private function resolveSettingForOrder(Order $order): ?ShipstationSetting
    {
        if ($order->shipstation_setting_id) {
            $setting = ShipstationSetting::find($order->shipstation_setting_id);

            if ($setting && $setting->client_id && $setting->client_secret) {
                return $setting;
            }
        }

        return ShipstationSetting::getActive();
    }

    private function generateModifiedDate()
    {
        return today()->startOfDay()->toIso8601String();
        // return now()->subMonth()->startOfMonth()->toIso8601String();
        // return now()->subDays(14)->toIso8601String();
    }

    public function makeOrderData($order)
    {
        // $orderId = $order['orderNumber'];
        $orderId = trim($order['orderNumber']);

        $orderDate = $this->makeBSTDate($order['orderDate']);
        $orderStatus = $order['orderStatus'];
        $postalCode = $order['shipTo']['postalCode'];
        $shipCity = $order['shipTo']['city'];
        $recipient_name = $order['shipTo']['name'];
        $shipAddress = $order['shipTo']['street1'].' '.$order['shipTo']['street2'].' '.$order['shipTo']['street3'];
        $country = $order['shipTo']['country'] ?? null;

        $source = data_get($order, 'advancedOptions.source');
        // $requestedShippingService =  in_array(31255, $order['tagIds'] ?? []);
        $requestedShippingService = in_array(
            config('services.shipstation.prime_order_tag_id'),  
            $order['tagIds'] ?? []
        );
        if ($requestedShippingService) {
            $source = 'Amazon_Prime';
        }
        $shipStationOrderId = $order['orderId'] ?? null;
        $isEbay = str_contains(strtolower((string) $source), 'ebay');

        $lineItemsSaved = 0;

        foreach ($order['items'] as $item) {

            if ($isEbay) {
                $parsed = ! empty($item['sku'])
                    ? $this->parseMaterialAndEdgingFromEbaySku($item['sku'])
                    : ['material' => 'Carpet', 'edging' => null];
                $material_type = $parsed['material'];
                $edging = $parsed['edging'];
            } else {
                $material_type = $this->determineMaterialType($item['name']);
                $edging = $this->extractEdgingColor($item['name']);
            }
            $vehicleInfo = $this->extractVehicleInfo($item['name']);

           $orderItemId = trim($item['orderItemId']);

            $order = Order::updateOrCreate([
                'order_id' => $orderId,
                'order_item_id' => $orderItemId
            ], [
                'sku' => $item['sku'],
                'date' => $orderDate,
                'order_number' => $order['orderId'],
                'status' => $orderStatus,
                'postal_code' => $postalCode,
                'city' => $shipCity,
                'recipient_name' => $recipient_name,
                'address' => $shipAddress,
                'product' => $item['productId'],
                'quantity' => $item['quantity'],
                'make_model' => $vehicleInfo['clean_name'],
                'material_type' => $material_type,
                'edging' => $edging,
                'source' => $source,
                'country' => $country,
                'shipstation_setting_id' => $this->shipstation_setting_id,
            ]);
            //if recently created, try to link to return
            if ($order->wasRecentlyCreated) {
                $this->updateAmazonReturn($order);
                $this->syncSkuInProduct($order);
            }

            $lineItemsSaved++;
        }

        return $lineItemsSaved;
    }
    /** Ca / Ru → material; Bl, Bl2, Gr, Gr2, Wh → edging (SKU parts separated by _ or -). */
    private function parseMaterialAndEdgingFromEbaySku(string $sku): array
    {
        $material = null;
        $edging = null;
        foreach (preg_split('/[_\-]/', $sku, -1, PREG_SPLIT_NO_EMPTY) as $seg) {
            $u = strtoupper(trim($seg));
            if ($u === 'BL2') {
                $edging = 'Blue';
            } elseif ($u === 'GR2') {
                $edging = 'Grey';
            } elseif ($u === 'BL') {
                $edging = 'Black';
            } elseif ($u === 'GR') {
                $edging = 'Green';
            } elseif ($u === 'WH') {
                $edging = 'White';
            } elseif ($u === 'RE') {
                $edging = 'Red';
            } elseif ($u === 'RU') {
                $material = 'Rubber';
            } elseif ($u === 'CA') {
                $material = 'Carpet';
            }else{
                $material = 'Carpet';
            }
        }

        return ['material' => $material, 'edging' => $edging];
    }

    private function syncSkuInProduct($order)
    {
        return ProductSetting::whereName($order->make_model)
            ->whereJsonDoesntContain('skus', $order->sku)
            ->update([
                'skus' => DB::raw("JSON_ARRAY_APPEND(skus, '$', '{$order->sku}')")
            ]);
    }
    private function updateAmazonReturn(Order $order): void
    {
        $return = AmazonReturn::whereReceived(true)
            ->whereDoesntHave('relatedOrder')
            ->where(function (Builder $query) use ($order) {
                $query->where('sku', $order->sku)
                    ->orWhere(function (Builder $query) use ($order) {
                        $query->where('item_name', $order->make_model)
                            ->where('material_type', $order->material_type);
                    });
            })->first();

        if ($return) {
            $order->update(['amazon_return_id' => $return->id]);

            return;
        }

        $this->linkOrderToPrestock($order);
    }

    /**
     * When no Amazon return matches, reserve stock from prestock if product name + material match.
     */
    private function linkOrderToPrestock(Order $order): void
    {
        if ($order->amazon_return_id !== null || $order->prestock_id !== null) {
            return;
        }
        
        $name = trim((string) $order->make_model);
        if ($name === '' || $order->material_type === null) {
            return;
        }
        
        DB::transaction(function () use ($order, $name) {
            $order->refresh();
            
            if ($order->amazon_return_id !== null || $order->prestock_id !== null) {
                return;
            }

            $prestock = Prestock::query()
                ->where('stock', '>', 0)
                ->where('material', $order->material_type)
                ->whereHas('productSetting', function (Builder $q) use ($name) {
                    $q->whereRaw('LOWER(TRIM(product_settings.name)) = ?', [mb_strtolower($name)]);
                })
                ->lockForUpdate()
                ->orderBy('id')
                ->first();

            if (! $prestock) {
                return;
            }

            $prestock->decrement('stock');
            $order->update(['prestock_id' => $prestock->id]);
        });
    }

    private function makeBSTDate($dateTime)
    {
        return Carbon::parse($dateTime, 'America/Los_Angeles')
            ->setTimezone('Europe/London')
            ->toDateTimeString();
    }

    public function extractEdgingColor($productName)
    {
        if (empty($productName)) {
            return null;
        }

        $productName = trim($productName);

        // Look for pattern: [Color] Edging, [Material]
        if (preg_match('/([A-Za-z]+)\s+(?:Edging|Egding),\s*(Carpet|Rubber)/i', $productName, $matches)) {
            return trim($matches[1]);
        }

        // Look for pattern: [Color] Edging
        if (preg_match('/([A-Za-z]+)\s+(?:Edging|Egding)/i', $productName, $matches)) {
            return trim($matches[1]);
        }
        //Look for pattern: [COLOR] Carpet|Rubber
        if (preg_match('/([A-Za-z]+)\s+(Carpet|Rubber)/i', $productName, $matches)) {
            return trim($matches[1]);
        }

        return null;
    }

    public function determineMaterialType($productName)
    {
        if (empty($productName)) {
            return 'Carpet'; // Default to Carpet
        }

        $productName = trim($productName);

        // Check if product name contains "Carpet" or "Rubber"
        // Look for patterns like "Edging, Carpet" or "Edging, Rubber"
        if (preg_match('/Edging,\s*Carpet/i', $productName)) {
            return 'Carpet';
        } elseif (preg_match('/Edging,\s*Rubber/i', $productName)) {
            return 'Rubber';
        }

        // Also check for standalone "Carpet" or "Rubber" at the end
        if (preg_match('/Carpet$/i', $productName)) {
            return 'Carpet';
        } elseif (preg_match('/Rubber$/i', $productName)) {
            return 'Rubber';
        }

        // Non-vehicle catalogue: Matting rolls are Rubber
        if (preg_match('/Matting\s+Roll/i', $productName)) {
            return 'Rubber';
        }

        // If no clear indicator, default to Carpet
        return 'Carpet';
    }

    // public function extractVehicleInfo($productName)
    // {
        //     if (empty($productName)) {
        //         return ['clean_name' => '', 'vehicle_info' => ''];
        //     }

        //     $productName = trim($productName);
        //     $lower = mb_strtolower($productName);

        //     if (str_contains($lower, 'seller discount') || str_contains($lower, 'platform discount')) {
        //         return ['clean_name' => $productName, 'vehicle_info' => $productName];
        //     }

        //     if (preg_match('/\bUniversal\b/i', $productName)) {
        //         return ['clean_name' => 'Universal fit', 'vehicle_info' => 'Universal fit'];
        //     }

        //     // GCM / MatTrax non-vehicle catalogue (garage roll, industrial sheet — no car line to extract)
        //     if (preg_match('/\b(?:GCM|MatTrax)\b/i', $productName)
        //         && preg_match('/\b(?:Rubber\s+Matting\s+Roll|Matting\s+Roll|Coin\s+Rubber\s+Flooring|Garage\s+Floor,\s*Van,\s*Gym|industrial\s+non\s+slip|waterproof\s+sheet)\b/i', $productName)) {
        //         return ['clean_name' => 'Rubber matting roll', 'vehicle_info' => 'Rubber matting roll'];
        //     }

        //     // Home / furniture listings mistaken for automotive (no vehicle fitment)
        //     if (preg_match('/\b(?:divan\s+bed|mattress|bedstead|plush\s+velvet\s+divan|drawer\s+storage\s+options)\b/i', $productName)
        //         && ! preg_match('/\b(?:car\s+mat|floor\s+mat|compatible\s+with|boot\s+liner)\b/i', $lower)) {
        //         $short = preg_replace('/\s*[–—]\s*.*$/u', '', $productName);
        //         $short = trim(mb_substr($short, 0, 100));

        //         return ['clean_name' => $short, 'vehicle_info' => $short];
        //     }

        //     $brands = 'GCM|MatTrax';

        //     // Stop before marketing / product blurb (longest phrases first)
        //     $stopBefore = '(?=\s+(?:Full\s+Coverage\s+Van\s+Protection|Full\s+Coverage\s+Floor\s+Protection|Full\s+Coverage\s+Protection|Full\s+Floor\s+Protection|Car\s+Floor\s+Protection|Full\s+Coverage\s+Floor|Full\s+Floor\s+Coverage|Full\s+Floor\s+Protect|Coverage\s+Floor\s+Protection|Coverage\s+Floor|Floor\s+Protection|Floor\s+Protect|Full\s+Protection|Full\s+Coverage|Floor\s+Coverage|Heavy\s+Duty|Clips\s+Easy|Easy\s+to\s+Clean|Easy[\p{Pd}]?to[\p{Pd}]?Clean|Easy\s+Clean|All-Weather|Anti[\p{Pd}]?Slip|Anti\s+Slip|Tailored|Set\s+with|Rubber\s+Car|Van\s+Mat)\b|\s+Full\b|\s+Car\s+Floor\b|\s+Floor\s+Mats\b|\s*\p{Pd}\s*Anti\b|(?<=[a-z])-Anti\b|$)';

        //     $veh = null;

        //     // Plain marketplace title: "<Vehicle> - Black Rubber…" / "… 3mm Thick" (not " - Does Not Fit …")
        //     if (! preg_match('/\b(?:' . $brands . ')\b/i', $productName)
        //         && preg_match(
        //             '/^(?P<veh>.+?)\s*[\p{Pd}]\s*(?!Does\s+Not\s+Fit\b)(?:Black|White|Red|Blue|Grey|Gray|Rubber|Carpet|Tailored|Anti[\p{Pd}]?Slip|Anti\s+Slip|\d+\s*mm|Mats\b|Mat\s+with)/iu',
        //             $productName,
        //             $m
        //         )) {
        //         $veh = $m['veh'];
        //     }

        //     // Title ending with stray dash only: "Renault Trafic 2014 to Present -"
        //     if ($veh === null && ! preg_match('/\b(?:' . $brands . ')\b/i', $productName)
        //         && preg_match('/^(?P<veh>.+?)\s*[\p{Pd}]+\s*$/u', $productName, $m)) {
        //         $veh = $m['veh'];
        //     }

        //     // Tailored Carpet-Rubber format
        //     if (preg_match(
        //         '/\b(?:' . $brands . ')\s+Tailored\s+.+?\s+Carpet-Rubber\s+Car Mats for\s+(?P<veh>.+?)\s*\([^,]+Edging,\s*(?:Carpet|Rubber)\)/iu',
        //         $productName,
        //         $m
        //     )) {
        //         $veh = $m['veh'];
        //     }

        //     // GCM - Vivaro 2014-2019 Floor Mats Full Coverage… (vehicle before Floor Mats; not "Car/Van Floor Mats …" — that would capture "Car" from GCM-Car Floor Mats Peugeot…)
        //     if ($veh === null && preg_match(
        //         '/\b(?:' . $brands . ')\s*\p{Pd}\s*(?!\s*(?:Car|Van)\s+Floor\s+Mats\b)(?P<veh>.+?)\s+Floor\s+Mats\s+/iu',
        //         $productName,
        //         $m
        //     )) {
        //         $veh = $m['veh'];
        //     }

        //     // Primary: optional brand & dash, "Car/Van Floor Mats for <vehicle>"
        //     if ($veh === null && preg_match(
        //         '/\b(?:' . $brands . ')\s*\p{Pd}?\s*(?:(?:Car|Van)\s+)?(?:Floor\s+)?Mats\s+for\s+(?P<veh>.+?)' . $stopBefore . '/uix',
        //         $productName,
        //         $m
        //     )) {
        //         $veh = $m['veh'];
        //     }

        //     // No "for": "GCM … Car Floor Mats Peugeot 2008 …" (SKU-style titles)
        //     if ($veh === null && preg_match(
        //         '/\b(?:' . $brands . ')\s*\p{Pd}?\s*(?:Car|Van)\s+Floor\s+Mats\s+(?!for\s+)(?P<veh>[A-Za-z0-9].+?)' . $stopBefore . '/uix',
        //         $productName,
        //         $m
        //     )) {
        //         $veh = $m['veh'];
        //     }

        //     // Van Floor Mats for (explicit)
        //     if ($veh === null && preg_match(
        //         '/\b(?:' . $brands . ')\s*\p{Pd}?\s*Van\s+Floor\s+Mats\s+for\s+(?P<veh>.+?)' . $stopBefore . '/uix',
        //         $productName,
        //         $m
        //     )) {
        //         $veh = $m['veh'];
        //     }

        //     // Boot liner format (year range before bare year — \b\d{4}\+? wrongly stopped at first year in "2010-2018")
        //     // Note: trailing \b after a year fails when listing has "2022Full" (digit + letter are both "word" chars in PCRE)
        //     $bootYearTail = '(?:\b\d{4}\s*[\p{Pd}]\s*\d{4}(?=[^\d]|$)|\b\d{4}\s*(?:to\s+\w+|Present)\b|\b\d{4}\s*\+|\b\d{4}\+(?=\s|\b)|\bPresent\b|\b\d{4}\b(?=\s+Fit\b))';
        //     if ($veh === null && preg_match(
        //         '/(?P<veh>Boot.+?Compatible\s+with\s+.+?' . $bootYearTail . ')/iu',
        //         $productName,
        //         $m
        //     )) {
        //         $veh = $m['veh'];
        //     }

        //     // Legacy: "GCM - … Car Mats … for …" with ASCII dash clutter (keep narrow)
        //     if ($veh === null && preg_match(
        //         '/\b(?:' . $brands . ')\s*-\s*(?:Car|Van)?\s*(?:Floor Mats|Car Mats)\s+for\s+(?P<veh>.+?)(?=\s+-\s+|,|$)/i',
        //         $productName,
        //         $m
        //     )) {
        //         $veh = $m['veh'];
        //     }

        //     // Van floor mats full coverage
        //     if ($veh === null && preg_match(
        //         '/\b(?:' . $brands . ')\s*-\s*(?P<veh>.+?\sVan\sFloor Mats\s+\d{4}\+?)\s*-\s*Full\s+Coverage\b/i',
        //         $productName,
        //         $m
        //     )) {
        //         $veh = $m['veh'];
        //     }

        //     if ($veh === null) {
        //         $clean = $this->cleanVehicleName($productName);

        //         return [
        //             'clean_name' => $clean,
        //             'vehicle_info' => $clean,
        //         ];
        //     }

        //     $clean = $this->cleanVehicleName($veh);

        //     return [
        //         'clean_name' => $clean,
        //         'vehicle_info' => $clean,
        //     ];
    // }

    public function extractVehicleInfo($productName)
    {
        if (empty($productName)) {
            return ['clean_name' => '', 'vehicle_info' => ''];
        }

        $productName = trim($productName);
        $lower = mb_strtolower($productName);

        if (str_contains($lower, 'seller discount') || str_contains($lower, 'platform discount')) {
            return ['clean_name' => $productName, 'vehicle_info' => $productName];
        }

        // if (preg_match('/\bUniversal\b/i', $productName)) {
        //     return ['clean_name' => 'Universal fit', 'vehicle_info' => 'Universal fit'];
        // }

        // ✅ Universal handling (specific types first)
        if (stripos($productName, 'universal') !== false) {

            // Universal Boot Liner
            if (preg_match('/Universal\s+.*Boot\s+Liner\s*(?:Mat)?/i', $productName, $m)) {
                return [
                    'clean_name' => 'Universal Boot Liner Mat',
                    'vehicle_info' => 'Universal Boot Liner Mat'
                ];
            }

            // Universal Car Mats
            if (preg_match('/Universal\s+.*Car\s+Mats?/i', $productName, $m)) {
                return [
                    'clean_name' => 'Universal Car Mats',
                    'vehicle_info' => 'Universal Car Mats'
                ];
            }

            // Fallback
            return [
                'clean_name' => 'Universal fit',
                'vehicle_info' => 'Universal fit'
            ];
        }


        $brands = 'GCM|MatTrax';

        /*
        |--------------------------------------------------------------------------
        | ✅ 1. BOOT LINER (FINAL FIX - NO CLEANING)
        |--------------------------------------------------------------------------
        */
        if (preg_match(
            '/(?P<veh>Boot\s+Liner\s+Mat\s+Compatible\s+with\s+.+?(?:\d{4}(?:\s*[\p{Pd}]\s*\d{4}|\+)?|\bPresent\b))/iu',
            $productName,
            $m
        )) {
            $clean = trim(preg_replace('/\s+/u', ' ', $m['veh']));

            return [
                'clean_name' => $clean,
                'vehicle_info' => $clean,
            ];
        }

        /*
        |--------------------------------------------------------------------------
        | Non-vehicle catalogue
        |--------------------------------------------------------------------------
        */
        if (preg_match('/\b(?:GCM|MatTrax)\b/i', $productName)
            && preg_match('/\b(?:Rubber\s+Matting\s+Roll|Matting\s+Roll|Coin\s+Rubber\s+Flooring|Garage\s+Floor,\s*Van,\s*Gym|industrial\s+non\s+slip|waterproof\s+sheet)\b/i', $productName)) {
            $label = 'Rubber matting roll';

            // Append trailing variation details when present, e.g. "(Wide Ribbed, 3m x 1.5m)"
            if (preg_match('/\(([^()]+)\)\s*$/u', $productName, $variationMatch)) {
                $label .= ' (' . trim($variationMatch[1]) . ')';
            }

            return ['clean_name' => $label, 'vehicle_info' => $label];
        }

        /*
        |--------------------------------------------------------------------------
        | Home / furniture safeguard
        |--------------------------------------------------------------------------
        */
        if (preg_match('/\b(?:divan\s+bed|mattress|bedstead|plush\s+velvet\s+divan|drawer\s+storage\s+options)\b/i', $productName)
            && !preg_match('/\b(?:car\s+mat|floor\s+mat|compatible\s+with|boot\s+liner)\b/i', $lower)) {

            $short = preg_replace('/\s*[–—-]\s*.*$/u', '', $productName);
            $short = trim(mb_substr($short, 0, 100));

            return ['clean_name' => $short, 'vehicle_info' => $short];
        }

        /*
        |--------------------------------------------------------------------------
        | Stop words
        |--------------------------------------------------------------------------
        */
        $stopBefore = '(?=\s+(?:Full\s+Coverage\s+Van\s+Protection|Full\s+Coverage\s+Floor\s+Protection|Full\s+Coverage\s+Protection|Full\s+Floor\s+Protection|Car\s+Floor\s+Protection|Full\s+Coverage\s+Floor|Full\s+Floor\s+Coverage|Full\s+Floor\s+Protect|Coverage\s+Floor\s+Protection|Coverage\s+Floor|Floor\s+Protection|Floor\s+Protect|Full\s+Protection|Full\s+Coverage|Floor\s+Coverage|Heavy\s+Duty|Clips\s+Easy|Easy\s+to\s+Clean|Easy[\p{Pd}]?to[\p{Pd}]?Clean|Easy\s+Clean|All-Weather|Anti[\p{Pd}]?Slip|Anti\s+Slip|Tailored|Set\s+with|Rubber\s+Car|Van\s+Mat)\b|\s+Full\b|\s+Car\s+Floor\b|\s+Floor\s+Mats\b|\s*\p{Pd}\s*Anti\b|(?<=[a-z])-Anti\b|$)';

        $veh = null;

        /*
        |--------------------------------------------------------------------------
        | Generic extraction rules
        |--------------------------------------------------------------------------
        */

        // Plain title before dash
        if (!preg_match('/\b(?:' . $brands . ')\b/i', $productName)
            && preg_match('/^(?P<veh>.+?)\s*[\p{Pd}]\s*(?!Does\s+Not\s+Fit\b)/iu', $productName, $m)) {
            $veh = $m['veh'];
        }

        // Ending dash
        if ($veh === null && !preg_match('/\b(?:' . $brands . ')\b/i', $productName)
            && preg_match('/^(?P<veh>.+?)\s*[\p{Pd}]+\s*$/u', $productName, $m)) {
            $veh = $m['veh'];
        }

        // Carpet Rubber format
        if ($veh === null && preg_match(
            '/\b(?:' . $brands . ')\s+Tailored\s+.+?\s+Carpet-Rubber\s+Car Mats for\s+(?P<veh>.+?)\s*\([^,]+Edging,\s*(?:Carpet|Rubber)\)/iu',
            $productName,
            $m
        )) {
            $veh = $m['veh'];
        }

        // GCM - Vehicle Floor Mats
        if ($veh === null && preg_match(
            '/\b(?:' . $brands . ')\s*\p{Pd}\s*(?!\s*(?:Car|Van)\s+Floor\s+Mats\b)(?P<veh>.+?)\s+Floor\s+Mats\s+/iu',
            $productName,
            $m
        )) {
            $veh = $m['veh'];
        }

        // Mats for vehicle
        if ($veh === null && preg_match(
            '/\b(?:' . $brands . ')\s*\p{Pd}?\s*(?:(?:Car|Van)\s+)?(?:Floor\s+)?Mats\s+for\s+(?P<veh>.+?)' . $stopBefore . '/uix',
            $productName,
            $m
        )) {
            $veh = $m['veh'];
        }

        // Without "for"
        if ($veh === null && preg_match(
            '/\b(?:' . $brands . ')\s*\p{Pd}?\s*(?:Car|Van)\s+Floor\s+Mats\s+(?!for\s+)(?P<veh>[A-Za-z0-9].+?)' . $stopBefore . '/uix',
            $productName,
            $m
        )) {
            $veh = $m['veh'];
        }

        // Van mats
        if ($veh === null && preg_match(
            '/\b(?:' . $brands . ')\s*\p{Pd}?\s*Van\s+Floor\s+Mats\s+for\s+(?P<veh>.+?)' . $stopBefore . '/uix',
            $productName,
            $m
        )) {
            $veh = $m['veh'];
        }

        /*
        |--------------------------------------------------------------------------
        | Fallback
        |--------------------------------------------------------------------------
        */
        if ($veh === null) {
            $clean = $this->cleanVehicleName($productName);

            return [
                'clean_name' => $clean,
                'vehicle_info' => $clean,
            ];
        }

        $clean = $this->cleanVehicleName($veh);

        return [
            'clean_name' => $clean,
            'vehicle_info' => $clean,
        ];
    }

    private function cleanVehicleName(string $name): string
    {
        $name = str_replace("\r", ' ', $name);
        $name = trim(preg_replace('/\s+/u', ' ', $name));

        // Plain marketplace lines: "Car Mats for Smart forfour 2015-2022 – Tailored" (no GCM prefix)
        $name = preg_replace('/^(?:Car|Van)\s+Floor\s+Mats\s+for\s+/iu', '', $name);
        $name = preg_replace('/^(?:Car|Van)\s+Mats\s+for\s+/iu', '', $name);
        $name = preg_replace('/^Floor\s+Mats\s+for\s+/iu', '', $name);

        // Listing typo: "2006-2022Full" → split before marketing word
        $name = preg_replace('/(\d{4})Full\b/iu', '$1 Full', $name);

        // Boot / trunk liners: same style as mats (vehicle + years only)
        $name = preg_replace('/^Boot\b.*?\bCompatible\s+with\s+/iu', '', $name);

        // Fitment disclaimers (e.g. Land Rover Discovery … - Does Not Fit Sports Model)
        $name = preg_replace('/\s+[\p{Pd}]\s*Does\s+Not\s+Fit\b.*$/iu', '', $name);
        $name = preg_replace('/\s+Does\s+Not\s+Fit\b.*$/iu', '', $name);

        $phrases = [
            'Full Coverage Floor Protection',
            'Full Coverage Protection',
            'Full Floor Protection',
            'Full Floor Protect',
            'Full Floor Coverage Anti Slip',
            'Car Floor Protection',
            'Full Coverage Floor',
            'Full Coverage Protect',
            'Full Coverage',
            'Coverage Floor Protection',
            'Coverage Floor',
            'Coverage Protection',
            'Full Floor Coverage',
            'Floor Protection',
            'Floor Protect',
            'Floor Coverage',
            'Full Protection',
            'Heavy Duty for All-Weather',
            'Anti Slip & Fit Rubber Car Mats with Clips Easy to Clean Heavy Duty for All-Weather',
            'Anti Slip & Fit Car Mat with Clips Easy to Clean Car Carpet for All-Weather',
            'Anti Slip & Fit Mat with Clips Easy Clean Car Carpet All-Weather',
            'Anti Slip & Fit Van Mat with Clips Easy to Clean Van Carpet for All-Weather',
            'Anti Slip & Fit Car Mat with 8 Clips Easy to Clean Car Carpet for All-Weather',
            'Clips Easy to Clean Car Carpet',
            'Easy to Clean Car Carpet for All-Weather',
            'Easy Clean Car Carpet All-Weather',
            'Easy to Clean Car Carpet',
            'Easy to Clean',
            'Car Mats Floor Protection',
        ];

        foreach ($phrases as $p) {
            $name = preg_replace('/\s*' . preg_quote($p, '/') . '\b/iu', '', $name);
        }

        // Hyphenated product tail (e.g. "Protection-Anti Slip…")
        $name = preg_replace('/[\p{Pd}]+Anti\s+Slip.*$/iu', '', $name);
        $name = preg_replace('/^\s*Anti\s+Slip.*$/iu', '', $name);

        // Variant disclaimers (do not match "(Non Electric)" — only "Also for electric/hybrid" style)
        $name = preg_replace('/\s*\(\s*Also\s+for\s+[^)]+\)/iu', '', $name);
        $name = preg_replace('/\s*\([^)]*$/u', '', $name);

        // Trailing marketing tokens left after partial removes
        $name = preg_replace('/\s+\b(?:Anti|Slip|Heavy|Duty|All|Weather|Clips|Mat|Mats|Rubber|Carpet|Set|with|Fit)\b(?:\s+\b(?:Anti|Slip|Heavy|Duty|All|Weather|Clips|Mat|Mats|Rubber|Carpet|Set|with|Fit)\b)*\s*$/iu', '', $name);
        $name = preg_replace('/\s+\b(?:Floor|Protection|Protect|Coverage)\b\s*$/iu', '', $name);
        // Orphan "Car" / "Van" from "Car Floor Protection" style titles (e.g. "…To Present Car")
        $name = preg_replace('/\s+\b(?:Car|Van)\b\s*$/iu', '', $name);
        // Stray punctuation / dashes at end (incomplete titles, paste noise)
        $name = preg_replace('/[\s\p{Pd},]+$/u', '', $name);

        // "… With Clips – Tailored…" capture ends before Tailored but leaves this blurb + dash; strip after punct trim
        $name = preg_replace('/\s+With\s+Clips?\b\s*$/iu', '', $name);

        // Trailing "– Tailored" / "- Tailored" (eBay-style product line)
        $name = preg_replace('/\s*[\p{Pd}]+\s*Tailored\s*$/iu', '', $name);

        return trim(preg_replace('/\s+/u', ' ', $name));
    }

    public function shipOrder(Order $order, $barcodeNumber)
    {
        $setting = $this->resolveSettingForOrder($order);

        Log::info('[ShipStation Tracking] Update started', [
            'order_id' => $order->id,
            'marketplace_order_id' => $order->order_id,
            'shipstation_order_id' => $order->order_number,
            'tracking_number' => $barcodeNumber,
            'current_status' => $order->status,
            'generated_by' => $order->generated_by,
            'shipstation_setting_id' => $setting?->id,
            'shipstation_account' => $setting?->name,
        ]);

        if (! $setting || ! $setting->client_id || ! $setting->client_secret) {
            $error = ! $setting
                ? 'No ShipStation account/setting resolved for this order'
                : 'ShipStation credentials missing (client_id or client_secret empty)';

            Log::error('[ShipStation Tracking] Update failed — missing credentials', [
                'order_id' => $order->id,
                'marketplace_order_id' => $order->order_id,
                'shipstation_order_id' => $order->order_number,
                'tracking_number' => $barcodeNumber,
                'error' => $error,
            ]);

            return false;
        }

        if (blank($order->order_number)) {
            $error = 'Order has no shipstation order_number (orderId) — cannot call markasshipped';

            Log::error('[ShipStation Tracking] Update failed — missing shipstation order id', [
                'order_id' => $order->id,
                'marketplace_order_id' => $order->order_id,
                'tracking_number' => $barcodeNumber,
                'error' => $error,
            ]);

            return false;
        }

        if (blank($barcodeNumber)) {
            $error = 'Tracking number / barcode is empty — cannot call markasshipped';

            Log::error('[ShipStation Tracking] Update failed — missing tracking number', [
                'order_id' => $order->id,
                'marketplace_order_id' => $order->order_id,
                'shipstation_order_id' => $order->order_number,
                'error' => $error,
            ]);

            return false;
        }

        $payload = [
            'orderId' => $order->order_number,
            'carrierCode' => 'evri',
            'trackingNumber' => $barcodeNumber,
            'notifySalesChannel' => true,
        ];

        Log::info('[ShipStation Tracking] Calling markasshipped API', [
            'order_id' => $order->id,
            'endpoint' => $this->base_url.'/orders/markasshipped',
            'payload' => $payload,
        ]);

        try {
            $response = Http::withBasicAuth($setting->client_id, $setting->client_secret)
                ->post($this->base_url.'/orders/markasshipped', $payload);
        } catch (\Throwable $e) {
            Log::error('[ShipStation Tracking] Update failed — request exception', [
                'order_id' => $order->id,
                'marketplace_order_id' => $order->order_id,
                'shipstation_order_id' => $order->order_number,
                'tracking_number' => $barcodeNumber,
                'error' => $e->getMessage(),
                'exception' => get_class($e),
            ]);

            return false;
        }

        if ($response->successful()) {
            Log::info('[ShipStation Tracking] Update succeeded', [
                'order_id' => $order->id,
                'marketplace_order_id' => $order->order_id,
                'shipstation_order_id' => $order->order_number,
                'tracking_number' => $barcodeNumber,
                'http_status' => $response->status(),
                'response_body' => $response->json() ?? $response->body(),
            ]);

            return true;
        }

        $responseJson = $response->json();
        $error = $this->extractShipStationErrorMessage($responseJson, $response->body(), $response->status());

        Log::error('[ShipStation Tracking] Update failed', [
            'order_id' => $order->id,
            'marketplace_order_id' => $order->order_id,
            'shipstation_order_id' => $order->order_number,
            'tracking_number' => $barcodeNumber,
            'http_status' => $response->status(),
            'error' => $error,
            'response_body' => $response->body(),
            'payload' => $payload,
        ]);

        return false;
    }

    /**
     * Pull a readable reason from ShipStation error responses.
     */
    private function extractShipStationErrorMessage(mixed $json, string $rawBody, int $status): string
    {
        if (is_array($json)) {
            foreach (['Message', 'message', 'ExceptionMessage', 'exceptionMessage', 'error', 'Error', 'title', 'detail'] as $key) {
                if (! empty($json[$key]) && is_string($json[$key])) {
                    return $json[$key];
                }
            }

            if (! empty($json['errors']) && is_array($json['errors'])) {
                $parts = [];
                foreach ($json['errors'] as $item) {
                    if (is_string($item)) {
                        $parts[] = $item;
                    } elseif (is_array($item)) {
                        $parts[] = $item['message'] ?? $item['Message'] ?? json_encode($item);
                    }
                }
                if ($parts !== []) {
                    return implode('; ', $parts);
                }
            }
        }

        $trimmed = trim($rawBody);
        if ($trimmed !== '') {
            return strlen($trimmed) > 500 ? substr($trimmed, 0, 500).'…' : $trimmed;
        }

        return "ShipStation markasshipped failed with HTTP {$status}";
    }

}