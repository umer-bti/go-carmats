<?php

namespace App\Http\Controllers\Console\Order;

use App\Http\Controllers\Controller;
use App\Services\AmazonService;
use App\Services\shipStationService;
use Illuminate\Http\Request;

class AwsController extends Controller
{
    public $shipstationService,$amazonService;
    public function __construct(shipStationService $awsService,AmazonService $amazonService)
    {
        $this->shipstationService = $awsService;
        $this->amazonService = $amazonService;
    }
    public function index()
    {
        $this->shipstationService->getOrders();
        //$this->amazonService->getReturns();
    }
}
