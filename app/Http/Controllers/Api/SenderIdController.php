<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CreateSenderIdRequest;
use App\Models\Company;
use App\Models\SenderID;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SenderIdController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        /** @var Company|null $company */
        $company = $request->user();

        if (! $company) {
            return response()->json([
                'message' => 'Non authentifie',
            ], 401);
        }

        $senderIds = SenderID::query()
            ->where('company_id', $company->id)
            ->orderByDesc('created_at')
            ->get([
                'id',
                'nom',
                'statut',
                'created_at',
            ]);

        return response()->json([
            'sender_ids' => $senderIds,
        ], 200);
    }

    public function store(CreateSenderIdRequest $request): JsonResponse
    {
        /** @var Company|null $company */
        $company = $request->user();

        if (! $company) {
            return response()->json([
                'message' => 'Non authentifie',
            ], 401);
        }

        $nom = strtoupper($request->validated('nom'));

        $alreadyExists = SenderID::query()
            ->where('company_id', $company->id)
            ->where('nom', $nom)
            ->exists();

        if ($alreadyExists) {
            return response()->json([
                'message' => 'Vous avez déjà un SENDER_ID avec ce nom',
            ], 422);
        }

        $senderId = SenderID::query()->create([
            'company_id' => $company->id,
            'nom' => $nom,
            'statut' => 'en_attente',
        ]);

        return response()->json([
            'message' => 'SENDER_ID soumis avec succès, en attente de validation',
            'sender_id' => [
                'id' => $senderId->id,
                'nom' => $senderId->nom,
                'statut' => $senderId->statut,
                'created_at' => $senderId->created_at,
            ],
        ], 201);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        /** @var Company|null $company */
        $company = $request->user();

        if (! $company) {
            return response()->json([
                'message' => 'Non authentifie',
            ], 401);
        }

        $senderId = SenderID::query()
            ->where('company_id', $company->id)
            ->find($id);

        if (! $senderId) {
            return response()->json([
                'message' => 'SENDER_ID introuvable',
            ], 404);
        }

        return response()->json([
            'sender_id' => $senderId,
        ], 200);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        /** @var Company|null $company */
        $company = $request->user();

        if (! $company) {
            return response()->json([
                'message' => 'Non authentifie',
            ], 401);
        }

        $senderId = SenderID::query()
            ->where('company_id', $company->id)
            ->find($id);

        if (! $senderId) {
            return response()->json([
                'message' => 'SENDER_ID introuvable',
            ], 404);
        }

        if (in_array($senderId->statut, ['valide', 'suspendu'], true)) {
            return response()->json([
                'message' => 'Impossible de supprimer un SENDER_ID validé ou suspendu',
            ], 403);
        }

        $senderId->delete();

        return response()->json([
            'message' => 'SENDER_ID supprimé avec succès',
        ], 200);
    }
}
