<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\EvriTrackingTestController;
use App\Http\Controllers\Auth\LogoutController;
use App\Http\Controllers\Console\BatchManagement\BatchController as BatchManagementBatchController;
use App\Http\Controllers\Console\BatchManagement\CompletedController as BatchManagementBatchCompletedController;
use App\Http\Controllers\Console\Collection\CollectionController;
use App\Http\Controllers\Console\Dashboard\DashboardController;
use App\Http\Controllers\Console\LiveStats\LiveStatsController;
use App\Http\Controllers\Console\Order\DeletedOrdersController;
use App\Http\Controllers\Console\Order\OrderController;
use App\Http\Controllers\Console\Prestock\PrestockController;
use App\Http\Controllers\Console\Product\ProductController;
use App\Http\Controllers\Console\Report\ReportController;
use App\Http\Controllers\Console\Return\ReturnsController;
use App\Http\Controllers\Console\Setting\PermissionController as SettingPermissionController;
use App\Http\Controllers\Console\Setting\UserController as SettingUserController;
use App\Http\Controllers\Console\Settings\EvriSettingController;
use App\Http\Controllers\Console\Settings\ShipstationSettingController;
use App\Http\Controllers\Console\Settings\SettingsController;
use App\Http\Controllers\Console\ScanTracking\ScanTrackingController;
use App\Http\Controllers\Console\Shipmate\ShipmateController;
use App\Http\Controllers\Console\ShippingLabel\ShippingLabelController;
use App\Http\Controllers\Console\Stitcher\StitcherController;
use App\Http\Controllers\Console\Tracking\TrackingController;
use App\Http\Controllers\Console\UserManagement\RolesAndPermissionsController as UserManagementRolesAndPermissionsController;
use App\Http\Controllers\Console\UserManagement\UserController as UserManagementUserController;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;

Route::get('testName',function (){
    $service=new \App\Services\shipStationService();
    $name="GCM - Tailored Car Boot Liner Mat Compatible with TOYOTA CH-R 2017-2023 Fit Carpet Boot Mat, Full Protection Anti-Slip Easy Clean Mats (Green Edging, Carpet)
    ";
    dd($service->extractVehicleInfo($name));

});

Route::get('setup-project', function () {
    Artisan::call('storage:link');
    Artisan::call('cache:clear');
    Artisan::call('config:clear');
    Artisan::call('route:clear');
    Artisan::call('view:clear');
    Artisan::call('clear-compiled');

    return response()->json(['message' => 'Project links initiated successfully.']);
});

Route::get('migrate', function () {
    Artisan::call('migrate');

    return response()->json(['message' => 'Project links initiated successfully.']);
});

Route::get('evri-tracking/{barcode}', [EvriTrackingTestController::class, 'show'])
    ->where('barcode', '.*');

Route::get('/', function () {
    return to_route('console.liveStats.index');
})->name('home');

Route::get('/aws',[App\Http\Controllers\Console\Order\AwsController::class,'index']);

Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
Route::post('/login', [LoginController::class, 'login']);
Route::post('/logout', [LogoutController::class, 'logout'])->name('logout');

Route::middleware('auth')->group(function () {
    Route::prefix('console')->name('console.')->group(function () {

        Route::middleware('dashboard.access')->group(function () {
            Route::get('/dashboard/gate', [DashboardController::class, 'gate'])->name('dashboard.gate');
            Route::post('/dashboard/verify-password', [DashboardController::class, 'verifyPassword'])->name('dashboard.verify');
            Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
        });

        Route::prefix('live-stats')->name('liveStats.')->group(function () {
            Route::get('/', [LiveStatsController::class, 'index'])->name('index');
            Route::get('/data', [LiveStatsController::class, 'data'])->name('data');
        });

        Route::prefix('shipping-labels')->name('shippingLabels.')->middleware('permission:view labels')->group(function () {
            Route::get('/', [ShippingLabelController::class, 'index'])->name('index');

            Route::prefix('order/fetch')->group(function () {
                Route::post('/{id}', [ShippingLabelController::class, 'fetchOrder'])->name('fetchOrder');
                Route::post('/generate-and-print-order-label/{id}', [ShippingLabelController::class, 'generateAndPrintOrderLabel'])->name('generateAndPrintOrderLabel');
                Route::post('/generate-additional-label/{id}', [ShippingLabelController::class, 'generateAdditionalLabel'])->name('generateAdditionalLabel');
            });
        });

        Route::prefix('shipmate')->name('shipmate.')->middleware('permission:view shipmate')->group(function () {
            Route::get('/', [ShipmateController::class, 'index'])->name('index');

            Route::prefix('order/fetch')->group(function () {
                Route::post('/{id}', [ShipmateController::class, 'fetchOrder'])->name('fetchOrder');
                Route::post('/generate-and-print-order-label/{id}', [ShipmateController::class, 'generateAndPrintOrderLabel'])->name('generateAndPrintOrderLabel');
                Route::post('/generate-additional-label/{id}', [ShipmateController::class, 'generateAdditionalLabel'])->name('generateAdditionalLabel');
            });
        });

        Route::prefix('scan-tracking')->name('scanTracking.')->middleware('permission:view labels|view shipmate')->group(function () {
            Route::get('/', [ScanTrackingController::class, 'index'])->name('index');
        });

        Route::prefix('orders')->name('orders.')->middleware('permission:view orders')->group(function () {
            Route::get('/', [OrderController::class, 'index'])->name('index');
            Route::post('/import', [OrderController::class, 'importOrder'])->name('import')->middleware('permission:edit orders');
            Route::get('/format2-template', [OrderController::class, 'downloadFormat2Template'])->name('format2Template');
            Route::get('/custom-export', [OrderController::class, 'customExport'])->name('customExport');
            Route::delete('/bulk-delete', [OrderController::class, 'bulkDestroy'])->name('bulkDestroy')->middleware('permission:delete orders');
            Route::delete('/{id}', [OrderController::class, 'destroy'])->name('destroy')->middleware('permission:delete orders');
            Route::get('/return-details', [OrderController::class, 'getReturnDetails'])->name('returnDetails');
            Route::get('/prestock-match-details', [OrderController::class, 'getPrestockMatchDetails'])->name('prestockMatchDetails');
            Route::post('/unutilize-return', [OrderController::class, 'unutilizeReturn'])->name('unutilizeReturn')->middleware('permission:edit orders');
            
            Route::prefix('deleted')->name('deleted.')->middleware('permission:delete orders')->group(function () {
                Route::get('/', [DeletedOrdersController::class, 'index'])->name('index');
                Route::post('/restore', [DeletedOrdersController::class, 'restore'])->name('restore');
                Route::delete('/force-delete', [DeletedOrdersController::class, 'forceDelete'])->name('forceDelete');
            });
        });

        Route::prefix('tracking')->name('tracking.')->middleware('permission:view Tracking')->group(function () {
            Route::get('/', [TrackingController::class, 'index'])->name('index');
            Route::get('/awaiting-shipment-orders', [TrackingController::class, 'getAwaitingShipmentOrders'])->name('getAwaitingShipmentOrders');
            Route::post('/add-tracking', [TrackingController::class, 'addTracking'])->name('addTracking')->middleware('permission:create Tracking');
        });

        Route::prefix('replacements')->name('replacements.')->middleware('permission:view replacements')->group(function () {
            Route::get('/', [App\Http\Controllers\Console\Replacement\ReplacementController::class, 'index'])->name('index');
            Route::post('/', [App\Http\Controllers\Console\Replacement\ReplacementController::class, 'store'])->name('store')->middleware('permission:create replacements');
            Route::get('/search', [App\Http\Controllers\Console\Replacement\ReplacementController::class, 'search'])->name('search')->middleware('permission:create replacements');
            Route::get('/print', [App\Http\Controllers\Console\Replacement\ReplacementController::class, 'print'])->name('print')->middleware('permission:print replacements');
            Route::post('/mark-as-printed', [App\Http\Controllers\Console\Replacement\ReplacementController::class, 'markAsPrinted'])->name('markAsPrinted')->middleware('permission:print replacements');
            Route::delete('/{replacement}', [App\Http\Controllers\Console\Replacement\ReplacementController::class, 'destroy'])->name('destroy')->middleware('permission:delete replacements');
        });

        Route::prefix('batch-management')->name('batchManagement.')->middleware('permission:view batches')->group(function () {
            Route::prefix('batches')->name('batches.')->group(function () {
                Route::get('/', [BatchManagementBatchController::class, 'index'])->name('index');
                Route::post('/', [BatchManagementBatchController::class, 'store'])->name('store')->middleware('permission:create batches');
                            Route::get('/generate-unique-identifiers', [BatchManagementBatchController::class, 'generateUniqueIdentifiers'])->name('generateUniqueIdentifiers');
            Route::post('/check-product-files', [BatchManagementBatchController::class, 'checkProductFiles'])->name('checkProductFiles');
            Route::get('/{id}', [BatchManagementBatchController::class, 'show'])->name('show');
                Route::get('/{id}/download', [BatchManagementBatchController::class, 'downloadFiles'])->name('downloadFiles');
                Route::get('/{id}/export-orders', [BatchManagementBatchController::class, 'exportBatchOrders'])->name('exportOrders');
                Route::post('/mark-as-completed', [BatchManagementBatchController::class, 'markAsCompleted'])->name('markAsCompleted')->middleware('permission:edit batches');
                Route::post('/mark-as-processed', [BatchManagementBatchController::class, 'markAsProcessed'])->name('markAsProcessed')->middleware('permission:edit batches');
                Route::post('/mark-as-incomplete', [BatchManagementBatchController::class, 'markAsIncomplete'])->name('markAsIncomplete')->middleware('permission:edit batches');
            });

            Route::prefix('completed')->name('completed.')->group(function () {
                Route::get('/', [BatchManagementBatchCompletedController::class, 'index'])->name('index');
                Route::get('/process', [BatchManagementBatchCompletedController::class, 'process'])->name('process');
                Route::post('/upload-and-reorder', [BatchManagementBatchCompletedController::class, 'uploadAndReorder'])->name('uploadAndReorder')->middleware('permission:edit batches');
                Route::post('/store', [BatchManagementBatchCompletedController::class, 'store'])->name('store')->middleware('permission:edit batches');
            });
        });

        Route::prefix('user-management')->name('userManagement.')->group(function () {
            Route::prefix('users')->name('users.')->middleware('permission:view users')->group(function () {
                Route::get('/', [UserManagementUserController::class, 'index'])->name('index');
                Route::post('/', [UserManagementUserController::class, 'store'])->name('store')->middleware('permission:create users');
                Route::put('/{id}', [UserManagementUserController::class, 'update'])->name('update')->middleware('permission:edit users');
                Route::get('/{id}', [UserManagementUserController::class, 'show'])->name('show');
                Route::delete('/{id}', [UserManagementUserController::class, 'destroy'])->name('destroy')->middleware('permission:delete users');
            });

            Route::prefix('roles')->name('roles.')->middleware('permission:view roles')->group(function () {
                Route::get('/', [UserManagementRolesAndPermissionsController::class, 'index'])->name('index');
                Route::get('/create', [UserManagementRolesAndPermissionsController::class, 'create'])->name('create')->middleware('permission:create roles');
                Route::get('role/edit/{id}', [UserManagementRolesAndPermissionsController::class, 'edit'])->name('edit')->middleware('permission:edit roles');
                Route::post('/', [UserManagementRolesAndPermissionsController::class, 'store'])->name('store')->middleware('permission:create roles');
                Route::put('/{role}', [UserManagementRolesAndPermissionsController::class, 'update'])->name('update')->middleware('permission:edit roles');
                Route::delete('/{role}', [UserManagementRolesAndPermissionsController::class, 'destroy'])->name('destroy')->middleware('permission:delete roles');

                Route::prefix('permission')->name('permission.')->group(function () {
                    Route::get('/{role}', [SettingPermissionController::class, 'get'])->name('get');
                });
            });
        });

        Route::prefix('reports')->name('reports.')->middleware('permission:view reports')->group(function () {
            Route::get('/', [ReportController::class, 'index'])->name('index');
            Route::post('/verify', [ReportController::class, 'verify'])->name('verify')->middleware('permission:export reports');
            Route::post('/verify-bulk', [ReportController::class, 'verifyBulk'])->name('verifyBulk')->middleware('permission:export reports');
        });

        Route::prefix('products')->name('products.')->middleware('permission:view products')->group(function () {
            Route::get('/', [ProductController::class, 'index'])->name('index');
            Route::post('/', [ProductController::class, 'store'])->name('store')->middleware('permission:create products');
            Route::get('/show', [ProductController::class, 'show'])->name('show');
            Route::put('/', [ProductController::class, 'update'])->name('update')->middleware('permission:edit products');
            Route::delete('/', [ProductController::class, 'destroy'])->name('destroy')->middleware('permission:delete products');
            Route::post('/get-product-image', [ProductController::class, 'getProductImage'])->name('getProductImage');
            Route::post('/get-skus-by-name', [ProductController::class, 'getSkusByProductName'])->name('getSkusByName');
            Route::post('/export-dxf', [ProductController::class, 'exportDxfFiles'])->name('exportDxf');
        });

        Route::prefix('collections')->name('collections.')->middleware('permission:view collection')->group(function () {
            Route::get('/', [CollectionController::class, 'index'])->name('index');
            Route::post('/', [CollectionController::class, 'store'])->name('store')->middleware('permission:create collection');
            Route::get('/show', [CollectionController::class, 'show'])->name('show');
            Route::put('/', [CollectionController::class, 'update'])->name('update')->middleware('permission:edit collection');
            Route::delete('/', [CollectionController::class, 'destroy'])->name('destroy')->middleware('permission:delete collection');
        });

        // Route::prefix('stitchers')->name('stitchers.')->middleware(['permission:view stitchers', 'stitchers.access'])->group(function () {
        Route::prefix('stitchers')->name('stitchers.')->middleware(['permission:view stitchers'])->group(function () {
            Route::get('/gate', [StitcherController::class, 'gate'])->name('gate');
            Route::post('/verify-password', [StitcherController::class, 'verifyPassword'])->name('verify');
            Route::get('/', [StitcherController::class, 'index'])->name('index');
            Route::post('/', [StitcherController::class, 'store'])->name('store')->middleware('permission:create stitchers');
            Route::post('/{stitcher}/assign-order', [StitcherController::class, 'assignOrder'])->name('assignOrder')->middleware('permission:edit stitchers');
            Route::post('/{stitcher}/unassign-orders', [StitcherController::class, 'unassignOrders'])->name('unassignOrders')->middleware(['permission:delete stitchers','stitchers.access']);
            Route::put('/{stitcher}', [StitcherController::class, 'update'])->name('update')->middleware('permission:edit stitchers');
            Route::delete('/{stitcher}', [StitcherController::class, 'destroy'])->name('destroy')->middleware('permission:delete stitchers');
        });

        Route::prefix('returns')->name('returns.')->middleware('permission:view Returns')->group(function () {
            Route::get('/', [ReturnsController::class, 'index'])->name('index');
            Route::post('/mark-as-received', [ReturnsController::class, 'markAsReceived'])->name('markAsReceived')->middleware('permission:edit Returns');
            Route::post('/unmark-as-received', [ReturnsController::class, 'unmarkAsReceived'])->name('unmarkAsReceived')->middleware('permission:edit Returns');
            Route::post('/generate-label-barcode', [ReturnsController::class, 'generateLabelBarcode'])->name('generateLabelBarcode');
        });

        Route::prefix('prestock')->name('prestock.')->middleware('permission:view prestock')->group(function () {
            Route::get('/', [PrestockController::class, 'index'])->name('index');
            Route::post('/', [PrestockController::class, 'store'])->name('store')->middleware('permission:create prestock');
            Route::get('/{prestock}', [PrestockController::class, 'show'])->name('show')->middleware('permission:edit prestock');
            Route::put('/{prestock}', [PrestockController::class, 'update'])->name('update')->middleware('permission:edit prestock');
            Route::delete('/{prestock}', [PrestockController::class, 'destroy'])->name('destroy')->middleware('permission:delete prestock');
            Route::get('/print-label-data/{id}', [PrestockController::class, 'getLabelData'])
                ->name('print')->middleware('permission:edit prestock');
        });

        Route::prefix('settings')->name('settings.')->middleware('settings.access')->group(function () {

            Route::get('/gate', [SettingsController::class, 'gate'])->name('gate');
            Route::post('/verify-password', [SettingsController::class, 'verifyPassword'])->name('verify');

            Route::get('/', [SettingsController::class, 'index'])->name('index');

            Route::get('/profile', [SettingUserController::class, 'showProfile'])->name('profile');

            Route::prefix('user')->name('user.')->group(function () {
                Route::put('/profile/{user}', [SettingUserController::class, 'updateProfile'])->name('profile.update');
                Route::put('/password/{user}', [SettingUserController::class, 'updatePassword'])->name('password.update');
            });

            Route::prefix('evri')->name('evri.')->group(function () {
                Route::get('/', [EvriSettingController::class, 'index'])->name('index');
                Route::post('/', [EvriSettingController::class, 'store'])->name('store');
                Route::put('/{evri}', [EvriSettingController::class, 'update'])->name('update');
                Route::delete('/{evri}', [EvriSettingController::class, 'destroy'])->name('destroy');
                Route::post('/{evri}/set-active', [EvriSettingController::class, 'setActive'])->name('setActive');
            });

            Route::prefix('shipstation')->name('shipstation.')->group(function () {
                Route::get('/', [ShipstationSettingController::class, 'index'])->name('index');
                Route::post('/', [ShipstationSettingController::class, 'store'])->name('store');
                Route::put('/{shipstation}', [ShipstationSettingController::class, 'update'])->name('update');
                Route::delete('/{shipstation}', [ShipstationSettingController::class, 'destroy'])->name('destroy');
                Route::post('/{shipstation}/set-active', [ShipstationSettingController::class, 'setActive'])->name('setActive');
            });
        });
    });
});