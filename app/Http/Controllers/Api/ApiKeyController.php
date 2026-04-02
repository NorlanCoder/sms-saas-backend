<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ApiKey;
use App\Models\Company;
use App\Services\RsaKeyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ApiKeyController extends Controller
{
    public function __construct(private readonly RsaKeyService $rsaKeyService)
    {
    }

    public function index(Request $request): JsonResponse
    {
        /** @var Company|null $company */
        $company = $request->user();

        if (! $company) {
            return response()->json([
                'message' => 'Non authentifie',
            ], 401);
        }

        $apiKey = $company->apiKeys()->active()->latest('created_at')->first();

        if (! $apiKey) {
            return response()->json([
                'message' => 'Aucune clé active trouvée',
            ], 404);
        }

        return response()->json([
            'public_key' => $apiKey->public_key,
            'statut' => $apiKey->statut,
            'created_at' => $apiKey->created_at,
        ], 200);
    }

    public function regenerate(Request $request): JsonResponse
    {
        /** @var Company|null $company */
        $company = $request->user();

        if (! $company) {
            return response()->json([
                'message' => 'Non authentifie',
            ], 401);
        }

        $keys = DB::transaction(function () use ($company): array {
            $company->apiKeys()->active()->update(['statut' => 'révoquée']);

            $keys = $this->rsaKeyService->generateKeyPair();

            ApiKey::query()->create([
                'company_id' => $company->id,
                'public_key' => $keys['public_key'],
                'statut' => 'active',
            ]);

            return $keys;
        });

        return response()->json([
            'message' => 'Clés régénérées avec succès',
            'public_key' => $keys['public_key'],
            'private_key' => $keys['private_key'],
        ], 200);
    }
}
