<?php

use App\Http\Controllers\GatewayController;
use Illuminate\Support\Facades\Route;

Route::middleware("gateway.ratelimit")->group(function () {

    Route::get("/health", fn() => response()->json([
        "status"    => "ok",
        "service"   => "api-gateway",
        "timestamp" => now()->toISOString(),
    ]));

    Route::any("/users", [GatewayController::class, "proxyUsers"]);
    Route::any("/users/{id}", [GatewayController::class, "proxyUsers"]);

    Route::any("/orders", [GatewayController::class, "proxyOrders"]);
    Route::any("/orders/{id}", [GatewayController::class, "proxyOrders"]);
});