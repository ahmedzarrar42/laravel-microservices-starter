<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class GatewayRateLimit
{
    private const MAX_REQUESTS  = 60;
    private const WINDOW_SECONDS = 60;

    public function handle(Request $request, Closure $next): Response
    {
        $key   = 'rate_limit:' . ($request->bearerToken() ?? $request->ip());
        $count = Cache::get($key, 0);

        if ($count >= self::MAX_REQUESTS) {
            return response()->json([
                'message'     => 'Too many requests.',
                'retry_after' => self::WINDOW_SECONDS,
            ], 429)->withHeaders([
                'X-RateLimit-Limit'     => self::MAX_REQUESTS,
                'X-RateLimit-Remaining' => 0,
                'Retry-After'           => self::WINDOW_SECONDS,
            ]);
        }

        Cache::add($key, 0, self::WINDOW_SECONDS);
        Cache::increment($key);

        return $next($request)->withHeaders([
            'X-RateLimit-Limit'     => self::MAX_REQUESTS,
            'X-RateLimit-Remaining' => self::MAX_REQUESTS - $count - 1,
        ]);
    }
}
