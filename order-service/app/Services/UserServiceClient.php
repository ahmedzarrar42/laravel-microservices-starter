<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class UserServiceClient
{
    private string $baseUrl;
    private const CACHE_TTL = 300; // 5 minutes

    public function __construct()
    {
        $this->baseUrl = config('services.user_service_url');
    }

    public function getUser(int $userId): ?array
    {
        // Cache user data to avoid repeated REST calls
        return Cache::remember(
            "user_service:user:{$userId}",
            self::CACHE_TTL,
            function () use ($userId) {
                try {
                    $response = Http::withHeaders([
                        'Accept'    => 'application/json',
                        'X-Service' => 'order-service',
                    ])
                    ->timeout(5)
                    ->get("{$this->baseUrl}/users/{$userId}");

                    if ($response->successful()) {
                        return $response->json();
                    }

                    Log::warning("User service returned {$response->status()} for user {$userId}");
                    return null;

                } catch (\Exception $e) {
                    Log::error('User service unavailable', ['error' => $e->getMessage()]);
                    return null;
                }
            }
        );
    }

    public function invalidateUserCache(int $userId): void
    {
        Cache::forget("user_service:user:{$userId}");
    }
}
