<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class ApiLogger
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $startedAt = microtime(true);

        Log::channel('api')->info('Incoming API request', [
            'timestamp' => now()->toIso8601String(),
            'method' => $request->method(),
            'url' => $request->fullUrl(),
            'ip' => $request->ip(),
            'headers' => [
                'content_type' => $request->header('Content-Type'),
                'authorization' => $this->maskAuthorization($request->header('Authorization')),
            ],
            'body' => $this->maskSensitiveData($request->all()),
        ]);

        $response = $next($request);

        Log::channel('api')->info('Outgoing API response', [
            'timestamp' => now()->toIso8601String(),
            'method' => $request->method(),
            'url' => $request->fullUrl(),
            'status_code' => $response->getStatusCode(),
            'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
        ]);

        return $response;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function maskSensitiveData(array $payload): array
    {
        $sensitiveKeys = [
            'password',
            'private_key',
            'public_key',
        ];

        foreach ($payload as $key => $value) {
            if (in_array(strtolower((string) $key), $sensitiveKeys, true)) {
                $payload[$key] = '***';
                continue;
            }

            if (is_array($value)) {
                $payload[$key] = $this->maskSensitiveData($value);
            }
        }

        return $payload;
    }

    private function maskAuthorization(?string $authorization): ?string
    {
        if (! $authorization) {
            return null;
        }

        if (str_starts_with($authorization, 'Bearer ')) {
            return 'Bearer ***';
        }

        return '***';
    }
}
