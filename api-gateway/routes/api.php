<?php

use App\Http\Controllers\GatewayController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Gateway Routes
| All requests are proxied to the appropriate microservice
|--------------------------------------------------------------------------
*/

Route::middleware('gateway.ratelimit')->group(function () {

    // Health check
    Route::get('/health', fn() => response()->json([
        'status'    => 'ok',
        'service'   => 'api-gateway',
        'timestamp' => now()->toISOString(),
    ]));

    // Proxy to User Service
    Route::any('/users/{path?}', [GatewayController::class, 'proxyUsers'])
        ->where('path', '.*');

    // Proxy to Order Service
    Route::any('/orders/{path?}', [GatewayController::class, 'proxyOrders'])
        ->where('path', '.*');
});
