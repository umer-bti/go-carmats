<?php

use Illuminate\Support\Facades\Schedule;
use App\Jobs\FetchAwsOrders;


Schedule::job(new FetchAwsOrders)->everyFiveMinutes();
Schedule::command('app:return-orders')->hourly();
// Schedule::command('evri:verify-shipments')->everyThirtyMinutes()->withoutOverlapping();
