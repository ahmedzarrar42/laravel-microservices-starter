<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GatewayController extends Controller
{
    private array $services = [];

    public function __construct()
    {
        $this->services = [
            'users'  => config('services.user_service_url'),
            'orders' => config('services.order_service_url'),
        ];
    }

    public function proxyUsers(Request $request, string $path = ''): JsonResponse
    {
        return $this->proxy('users', $request, $path);
    }

    public function proxyOrders(Request $request, string $path = ''): JsonResponse
    {
        return $this->proxy('orders', $request, $path);
    }

    private function proxy(string $service, Request $request, string $path): JsonResponse
    {
        $baseUrl = $this->services[$service] ?? null;

        if (! $baseUrl) {
            return response()->json(['message' => "Service '{$service}' not configured."], 503);
        }

        $url = rtrim($baseUrl, '/') . $request->getPathInfo();

        // Forward auth token if present
        $headers = [
            'Accept'       => 'application/json',
            'X-Request-ID' => $request->header('X-Request-ID', uniqid('req_')),
            'X-Gateway'    => 'laravel-api-gateway',
        ];

        if ($request->bearerToken()) {
            $headers['Authorization'] = 'Bearer ' . $request->bearerToken();
        }

        try {
            $response = Http::withHeaders($headers)
                ->timeout(10)
                ->{strtolower($request->method())}($url, $request->all());

            Log::info("Gateway proxy: {$request->method()} {$url}", [
                'status'     => $response->status(),
                'service'    => $service,
                'request_id' => $headers['X-Request-ID'],
            ]);

            return response()->json(
                $response->json(),
                $response->status()
            );
        } catch (\Exception $e) {
            Log::error("Gateway proxy error: {$service}", [
                'url'     => $url,
                'error'   => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Service temporarily unavailable.',
                'service' => $service,
            ], 503);
        }
    }
}
