<?php

namespace App\Services;

use App\Models\AmazonReturn;
use Illuminate\Support\Facades\Http;
use Carbon\Carbon;

class AmazonService
{
    private $client_id, $client_secret, $refresh_token;
    public function __construct()
    {
        $this->client_id = config('amazon.client_id');
        $this->client_secret = config('amazon.client_secret');
        $this->refresh_token = config('amazon.refresh_token');
    }
    public function getReturns()
    {

        try {
            $accessToken = $this->getAccessToken();
            $reportId = $this->createReport($accessToken);

            if (!$reportId) {
                return null;
            }

            //wait for report to be done
            $reportDocument = $this->waitForReport($reportId, $accessToken);

            if ($reportDocument && isset($reportDocument['reportDocumentId'])) {
                //get data from report document
                $documentData = $this->getReportDocument($reportDocument['reportDocumentId'], $accessToken);
                if (isset($documentData['url'])) {
                    $xml = file_get_contents($documentData['url']);
                    $xmlArray = json_decode(json_encode(simplexml_load_string($xml)), true);

                   $this->syncToDatabase($xmlArray['Message']['return_details'] ?? []);

                } else {
                    logger("❌ Document URL missing.");
                }
            } else {
                logger("❌ Report not ready or failed.");
            }
        } catch (\Exception $e) {
            logger()->error("Error: " . $e->getMessage());
        }
        return null;
    }

    /**
     * Step 1: Get Access Token
     */
    private function getAccessToken(): string
    {

        $response = Http::withBody(
            'grant_type=refresh_token'.
            '&client_id='.$this->client_id .
            '&client_secret='.$this->client_secret.
            '&refresh_token='.$this->refresh_token,
            'application/x-www-form-urlencoded'
        )->post('https://api.amazon.com/auth/o2/token');


        if ($response->failed()) {
            throw new \Exception('Failed to get access token: ' . $response->body());
        }

        return $response->json('access_token');
    }

    /**
     * Step 2: Create Report
     */
    private function createReport(string $accessToken): ?string
    {

        $marketplaceId = 'A1F83G8C2ARO7P';
        $reportType = 'GET_XML_RETURNS_DATA_BY_RETURN_DATE';
        $endTime = Carbon::now()->toIso8601String();
        $startTime = Carbon::now()->subDays()->toIso8601String();

        $response = Http::withHeaders([
            'x-amz-access-token' => $accessToken,
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ])->post('https://sellingpartnerapi-eu.amazon.com/reports/2021-06-30/reports', [
            'reportType' => $reportType,
            'marketplaceIds' => [$marketplaceId],
            'dataStartTime' => $startTime,
            'dataEndTime' => $endTime,
        ]);

        if ($response->failed()) {
            throw new \Exception('Failed to create report: ' . $response->body());
        }

        return $response->json('reportId');
    }

    /**
     * Step 3: Poll report until status is DONE
     */
    private function waitForReport(string $reportId, string $accessToken): ?array
    {
        sleep(10);

        for ($i = 0; $i < 20; $i++) {
            $response = Http::withHeaders([
                'x-amz-access-token' => $accessToken,
                'Accept' => 'application/json',
            ])->get("https://sellingpartnerapi-eu.amazon.com/reports/2021-06-30/reports/{$reportId}");

            if ($response->failed()) {
                throw new \Exception('Error getting report: ' . $response->body());
            }

            $data = $response->json();
            $status = $data['processingStatus'] ?? 'UNKNOWN';
            logger("⏳ Status: {$status}");

            if ($status === 'DONE') {
                return $data;
            } elseif (in_array($status, ['CANCELLED', 'FATAL'])) {
                throw new \Exception("Report failed with status: {$status}");
            }

            sleep(5);
        }

        return null;
    }

    /**
     * Step 4: Get Report Document
     */
    private function getReportDocument(string $documentId, string $accessToken): array
    {

        $response = Http::withHeaders([
            'x-amz-access-token' => $accessToken,
            'Accept' => 'application/json',
        ])->get("https://sellingpartnerapi-eu.amazon.com/reports/2021-06-30/documents/{$documentId}");

        if ($response->failed()) {
            throw new \Exception('Failed to get document: ' . $response->body());
        }

        return $response->json();
    }

    public function syncToDatabase($data)
    {

        $shipStation = app(shipStationService::class);

        foreach ($data as $return) {
            $rawItemName = $return['item_details']['item_name'] ?? '';
            AmazonReturn::updateOrCreate(
                ['order_id' => $return['order_id'],'sku' => $return['item_details']['merchant_sku'] ?? $return['item_details'][0]['merchant_sku']],
                [
                    'return_request_date' => Carbon::parse($return['return_request_date'])->toDateTimeString(),
                    'status' => $return['return_request_status'],
                    'reason' => $return['item_details']['return_reason_code'] ?? $return['item_details'][0]['return_reason_code'],
                    'return_type' => $return['return_type'],
                    'tracking' => $return['label_details']['tracking_id'] ?? null,
                    'item_name' => $shipStation->extractVehicleInfo($rawItemName)['clean_name'],
                    'material_type' => $shipStation->determineMaterialType($rawItemName),
                    'edging' => $shipStation->extractEdgingColor($rawItemName),
                ]
            );

        }
    }
}
