<?php

namespace App\Http\Middleware;

use App\Models\ApiKey;
use App\Services\RsaKeyService;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyApiSignature
{
    public function __construct(private readonly RsaKeyService $rsaKeyService)
    {
    }

    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $publicKey = $request->header('X-Public-Key');
        $signature = $request->header('X-Signature');
        $timestamp = $request->header('X-Timestamp');

        if (! $publicKey || ! $signature || ! $timestamp) {
            return $this->unauthorized('Headers d\'authentification manquants');
        }

        if (! is_numeric($timestamp) || abs(now()->timestamp - (int) $timestamp) > 300) {
            return $this->unauthorized('Requête expirée');
        }

        $apiKey = ApiKey::query()
            ->active()
            ->where('public_key', $publicKey)
            ->with('company')
            ->first();

        if (! $apiKey) {
            return $this->unauthorized('Clé publique invalide ou révoquée');
        }

        $data = $request->method().$request->url().$timestamp;
        $isValid = $this->rsaKeyService->verifySignature($publicKey, $data, $signature);

        if (! $isValid) {
            return $this->unauthorized('Signature invalide');
        }

        $request->merge(['authenticated_company' => $apiKey->company]);

        return $next($request);
    }

    private function unauthorized(string $message): JsonResponse
    {
        return response()->json([
            'message' => $message,
        ], 401);
    }
}
