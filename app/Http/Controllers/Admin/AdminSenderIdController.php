<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SenderID;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class AdminSenderIdController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'statut' => ['nullable', 'in:en_attente,valide,rejete,suspendu'],
        ]);

        $statut = $validated['statut'] ?? 'en_attente';

        $senderIds = SenderID::query()
            ->with(['company:id,nom,email'])
            ->where('statut', $statut)
            ->orderBy('created_at')
            ->paginate(20);

        return response()->json($senderIds, 200);
    }

    public function approve(Request $request, int $id): JsonResponse
    {
        $senderId = SenderID::query()->find($id);

        if (! $senderId) {
            return response()->json([
                'message' => 'SENDER_ID introuvable',
            ], 404);
        }

        $senderId->update([
            'statut' => 'valide',
        ]);

        return response()->json([
            'message' => 'SENDER_ID validé avec succès',
            'sender_id' => [
                'id' => $senderId->id,
                'nom' => $senderId->nom,
                'statut' => $senderId->statut,
            ],
        ], 200);
    }

    public function reject(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'motif' => ['required', 'string'],
        ]);

        $senderId = SenderID::query()->find($id);

        if (! $senderId) {
            return response()->json([
                'message' => 'SENDER_ID introuvable',
            ], 404);
        }

        $payload = [
            'statut' => 'rejete',
        ];

        if (Schema::hasColumn('sender_ids', 'motif_rejet')) {
            $payload['motif_rejet'] = $validated['motif'];
        }

        $senderId->update($payload);

        return response()->json([
            'message' => 'SENDER_ID rejeté',
            'sender_id' => [
                'id' => $senderId->id,
                'nom' => $senderId->nom,
                'statut' => $senderId->statut,
            ],
        ], 200);
    }

    public function suspend(Request $request, int $id): JsonResponse
    {
        $senderId = SenderID::query()->find($id);

        if (! $senderId) {
            return response()->json([
                'message' => 'SENDER_ID introuvable',
            ], 404);
        }

        $senderId->update([
            'statut' => 'suspendu',
        ]);

        return response()->json([
            'message' => 'SENDER_ID suspendu',
            'sender_id' => [
                'id' => $senderId->id,
                'nom' => $senderId->nom,
                'statut' => $senderId->statut,
            ],
        ], 200);
    }
}
