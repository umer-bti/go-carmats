<?php

namespace App\Services;

use App\Models\EvriSetting;
use App\Models\Order;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ShippingLabelService
{
    public $shipStationService;

    public function __construct(shipStationService $shipStationService)
    {
        $this->shipStationService = $shipStationService;
    }

    public function generateLabel(Order $order)
    {
        $evri = EvriSetting::getActive();
        if (! $evri) {
            return response()->json([
                'success' => false,
                'error' => 'No active Evri account or not configured. Set an account as active in Settings → Evri.',
            ]);
        }

        return $this->generateEvriLabel($order, $evri);
    }

    /**
     * Additional label: new Evri shipment (unique customer reference), print PDF only — not stored on order.
     */
    public function generateAdditionalLabel(Order $order)
    {
        $evri = EvriSetting::getActive();
        if (! $evri) {
            return response()->json([
                'success' => false,
                'error' => 'No active Evri account or not configured. Set an account as active in Settings → Evri.',
            ]);
        }

        return $this->createAdditionalEvriLabel($order, $evri);
    }

    private function createAdditionalEvriLabel(Order $order, EvriSetting $evri)
    {
        $maxCount = (int) config('evri.max_label_print_count', 5);
        $currentCount = (int) ($order->label_count ?? 0);

        if ($currentCount >= $maxCount) {
            return response()->json([
                'success' => false,
                'error' => 'Maximum additional labels (' . $maxCount . ') reached for this order.',
                'label_count' => $currentCount,
            ]);
        }

        $nextCount = $currentCount + 1;
        $parcelResult = $this->requestEvriLabel($order, $evri, $nextCount);

        if ($parcelResult instanceof \Illuminate\Http\JsonResponse) {
            return $parcelResult;
        }

        $labelCount = $order->recordLabelPrinted();

        return response()->json([
            'success' => true,
            'fileUrl' => $this->storeTemporaryLabelPdf($parcelResult['binary'], $order, $nextCount),
            'additional_label' => true,
            'tracking_number' => $parcelResult['barcodeNumber'],
            'label_count' => $labelCount,
        ]);
    }

    private function generateEvriLabel(Order $order, EvriSetting $evri)
    {
        $parcelResult = $this->requestEvriLabel($order, $evri);

        if ($parcelResult instanceof \Illuminate\Http\JsonResponse) {
            return $parcelResult;
        }

        $barcodeNumber = $parcelResult['barcodeNumber'];

        $storedFile = (new FileService)->createOrUpdate(
            inputFile: $parcelResult['binary'],
            relatedModel: $order,
            tag: 'labels',
            saveToDatabase: true
        );

        if ($order->status != 'shipped') {
            Log::info('[ShipStation Tracking] Evri label triggering ShipStation update', [
                'order_id' => $order->id,
                'marketplace_order_id' => $order->order_id,
                'tracking_number' => $barcodeNumber,
            ]);

            $shipStationUpdated = $this->shipStationService->shipOrder($order, $barcodeNumber);

            if ($shipStationUpdated) {
                Log::info('[ShipStation Tracking] Evri label ShipStation result', [
                    'order_id' => $order->id,
                    'marketplace_order_id' => $order->order_id,
                    'tracking_number' => $barcodeNumber,
                    'shipstation_updated' => true,
                ]);
            } else {
                Log::error('[ShipStation Tracking] Evri label ShipStation result — FAILED (see previous [ShipStation Tracking] error for reason)', [
                    'order_id' => $order->id,
                    'marketplace_order_id' => $order->order_id,
                    'tracking_number' => $barcodeNumber,
                    'shipstation_updated' => false,
                ]);
            }
        } else {
            Log::info('[ShipStation Tracking] Evri label skipped ShipStation update — already shipped', [
                'order_id' => $order->id,
                'marketplace_order_id' => $order->order_id,
                'existing_tracking_number' => $order->tracking_number,
            ]);
        }

        $order->update([
            'status' => 'shipped',
            'tracking_number' => $barcodeNumber,
            'generated_by' => 'evri',
        ]);

        return response()->json([
            'success' => true,
            'fileUrl' => asset('storage/' . $storedFile['path']),
            'label_count' => (int) ($order->label_count ?? 0),
        ]);
    }

    /**
     * @return array{binary: string, barcodeNumber: ?string}|\Illuminate\Http\JsonResponse
     */
    private function requestEvriLabel(Order $order, EvriSetting $evri, ?int $additionalCount = null)
    {
        $xml = $this->generateXml($order, $evri, $additionalCount);
        $evriUrl = config('evri.label_url');

        $response = Http::withHeaders([
            'Content-Type' => 'text/xml',
        ])->withBasicAuth($evri->api_key, $evri->api_secret)->send('POST', $evriUrl, [
            'body' => $xml,
        ]);

        if (! $response->ok()) {
            return response()->json([
                'success' => false,
                'error' => 'Evri API error: ' . $response->status(),
                'details' => $response->body(),
            ]);
        }

        $xmlBody = $response->body();

        try {
            $simple = @simplexml_load_string($xmlBody);
        } catch (\Throwable $e) {
            $simple = false;
        }

        if ($simple === false) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to parse Evri XML response',
                'details' => substr($xmlBody, 0, 5000),
            ]);
        }

        $namespaces = $simple->getDocNamespaces();
        $labelBase64 = null;
        if (empty($namespaces)) {
            $nodes = $simple->xpath('//labelImage');
        } else {
            $nodes = $simple->xpath('//*[local-name()="labelImage"]');
        }
        if (! empty($nodes) && isset($nodes[0])) {
            $labelBase64 = (string) $nodes[0];
        }

        $barcodeNumber = null;
        if (empty($namespaces)) {
            $barcodeNodes = $simple->xpath('//barcodeNumber');
        } else {
            $barcodeNodes = $simple->xpath('//*[local-name()="barcodeNumber"]');
        }
        if (! empty($barcodeNodes) && isset($barcodeNodes[0])) {
            $barcodeNumber = (string) $barcodeNodes[0];
        }

        if (empty($labelBase64)) {
            return response()->json([
                'success' => false,
                'error' => 'labelImage not found in Evri response',
                'details' => substr($xmlBody, 0, 5000),
            ]);
        }

        $binary = base64_decode($labelBase64);

        return [
            'binary' => $binary,
            'barcodeNumber' => $barcodeNumber,
        ];
    }

    private function storeTemporaryLabelPdf(string $binary, Order $order, int $count): string
    {
        $filename = $this->additionalLabelFileBaseName($order, $count) . '.pdf';
        $path = 'uploads/labels/temp/' . $filename;
        Storage::disk('public')->put($path, $binary);

        return asset('storage/' . $path);
    }

    private function additionalLabelFileBaseName(Order $order, int $count): string
    {
        $orderId = $this->sanitizeLabelFileSegment((string) ($order->order_id ?? 'order'));
        $itemId = $this->sanitizeLabelFileSegment((string) ($order->order_item_id ?: $order->order_id));

        return $orderId . '-' . $itemId . '-' . $count;
    }

    private function sanitizeLabelFileSegment(string $value): string
    {
        $safe = preg_replace('/[^A-Za-z0-9._-]/', '_', trim($value));

        return $safe !== '' ? $safe : 'unknown';
    }

    private function generateXml(Order $order, EvriSetting $evri, ?int $additionalCount = null): string
    {
        $clientId = $evri->client_id ?? '178';
        $clientName = $evri->client_name ?? 'Erde Ventus Ltd';
        $childClientId = $evri->child_client_id ?? '017';
        $childClientName = $evri->child_client_name ?? 'GCM';

        $firstName = '';
        $lastName = '';
        if (! empty($order->recipient_name)) {
            $exp = preg_split('/\s+/', trim($order->recipient_name));
            $firstName = $exp[0] ?? '';
            $lastName = implode(' ', array_slice($exp, 1)) ?: $firstName;
        }

        $parts = $this->splitAddress($order->address);

        $city = $order->city ?? '';
        $postCode = $order->postal_code ?? '';
        $countryCode = 'GB';
        $customerReference1 = $this->resolveCustomerReference($order, $additionalCount);
        $deliveryMessage = $order->description ?? '';

        $weightGrams = 1000;
        $length = 20;
        $width = 20;
        $depth = 20;
        $girth = 140;
        $combinedDimension = 190;
        $currency = 'GBP';
        $value = 1000;
        $numberOfParts = 1;
        $numberOfItems = (int) ($order->quantity ?? 1);
        $description = 'Carpet';
        $originOfParcel = 'GB';
        $nextDay = 'false';
        $expectedDespatchDate = now()->format('Y-m-d');
        $countryOfOrigin = 'GB';

        $creationDate = now()->format('Y-m-d\TH:i:s');

        $xml = '<deliveryRoutingRequest>' .
            '<clientId>' . htmlspecialchars($clientId, ENT_XML1 | ENT_COMPAT, 'UTF-8') . '</clientId>' .
            '<clientName>' . htmlspecialchars($clientName, ENT_XML1 | ENT_COMPAT, 'UTF-8') . '</clientName>' .
            '<childClientId>' . htmlspecialchars($childClientId, ENT_XML1 | ENT_COMPAT, 'UTF-8') . '</childClientId>' .
            '<childClientName>' . htmlspecialchars($childClientName, ENT_XML1 | ENT_COMPAT, 'UTF-8') . '</childClientName>' .
            "<creationDate>{$creationDate}</creationDate>" .
            '<sourceOfRequest>CLIENTWS</sourceOfRequest>' .
            '<deliveryRoutingRequestEntries>' .
            '<deliveryRoutingRequestEntry>' .
            '<addressValidationRequired>false</addressValidationRequired>' .
            '<customer>' .
            '<address>' .
            '<firstName>' . htmlspecialchars($firstName, ENT_XML1 | ENT_COMPAT, 'UTF-8') . '</firstName>' .
            '<lastName>' . htmlspecialchars($lastName, ENT_XML1 | ENT_COMPAT, 'UTF-8') . '</lastName>' .
            '<streetName>' . htmlspecialchars($parts[0] ?? '', ENT_XML1 | ENT_COMPAT, 'UTF-8') . '</streetName>' .
            '<addressLine1>' . htmlspecialchars($parts[1] ?? '', ENT_XML1 | ENT_COMPAT, 'UTF-8') . '</addressLine1>' .
            '<addressLine2>' . htmlspecialchars($parts[2] ?? '', ENT_XML1 | ENT_COMPAT, 'UTF-8') . '</addressLine2>' .
            '<city>' . htmlspecialchars($city, ENT_XML1 | ENT_COMPAT, 'UTF-8') . '</city>' .
            '<postCode>' . htmlspecialchars($postCode, ENT_XML1 | ENT_COMPAT, 'UTF-8') . '</postCode>' .
            "<countryCode>{$countryCode}</countryCode>" .
            '</address>' .
            '<customerReference1>' . htmlspecialchars($customerReference1, ENT_XML1 | ENT_COMPAT, 'UTF-8') . '</customerReference1>' .
            '<customerAlertGroup>0001</customerAlertGroup>' .
            '<deliveryMessage>' . htmlspecialchars($deliveryMessage, ENT_XML1 | ENT_COMPAT, 'UTF-8') . '</deliveryMessage>' .
            '</customer>' .
            '<parcel>' .
            "<weight>{$weightGrams}</weight>" .
            "<length>{$length}</length>" .
            "<width>{$width}</width>" .
            "<depth>{$depth}</depth>" .
            "<girth>{$girth}</girth>" .
            "<combinedDimension>{$combinedDimension}</combinedDimension>" .
            "<currency>{$currency}</currency>" .
            "<value>{$value}</value>" .
            "<numberOfParts>{$numberOfParts}</numberOfParts>" .
            "<numberOfItems>{$numberOfItems}</numberOfItems>" .
            "<description>{$description}</description>" .
            "<originOfParcel>{$originOfParcel}</originOfParcel>" .
            '</parcel>' .
            '<services>' .
            "<nextDay>{$nextDay}</nextDay>" .
            '</services>' .
            '<senderAddress>' .
            '<addressLine1>Unit 5 Aneal Business Centre</addressLine1>' .
            '<addressLine2>Cross Green Approach</addressLine2>' .
            '<addressLine3>Leeds</addressLine3>' .
            '<addressLine4>LS9 OSG</addressLine4>' .
            '</senderAddress>' .
            "<expectedDespatchDate>{$expectedDespatchDate}</expectedDespatchDate>" .
            "<countryOfOrigin>{$countryOfOrigin}</countryOfOrigin>" .
            '</deliveryRoutingRequestEntry>' .
            '</deliveryRoutingRequestEntries>' .
            '</deliveryRoutingRequest>';

        return $xml;
    }

    private function resolveCustomerReference(Order $order, ?int $additionalCount = null): string
    {
        $base = (string) ($order->order_item_id ?: $order->order_id ?: '');

        if ($additionalCount === null) {
            return $base;
        }

        $suffix = '-' . $additionalCount;

        return strlen($base . $suffix) <= 32
            ? $base . $suffix
            : substr($base, 0, max(1, 32 - strlen($suffix))) . $suffix;
    }

    private function splitAddress($address): array
    {
        $words = preg_split('/\s+/', trim($address));
        $parts = [];
        $currentPart = '';

        foreach ($words as $word) {
            if (strlen($currentPart . ' ' . $word) > 32) {
                $parts[] = trim($currentPart);
                $currentPart = $word;
                if (count($parts) >= 2) {
                    break;
                }
            } else {
                $currentPart .= ($currentPart ? ' ' : '') . $word;
            }
        }

        if ($currentPart !== '') {
            $parts[] = trim($currentPart);
        }

        return array_slice($parts, 0, 3);
    }
}
