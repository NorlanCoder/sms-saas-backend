<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\SmsLog;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SmsLogController extends Controller
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

        $filters = $request->validate([
            'statut' => ['nullable', 'in:envoye,echoue,en_attente'],
            'date_debut' => ['nullable', 'date_format:Y-m-d'],
            'date_fin' => ['nullable', 'date_format:Y-m-d'],
            'sender_id' => ['nullable', 'integer'],
            'destinataire' => ['nullable', 'string'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $perPage = (int) ($filters['per_page'] ?? 20);

        $query = SmsLog::query()
            ->where('company_id', $company->id)
            ->with(['sender:id,nom']);

        if (! empty($filters['statut'])) {
            $query->where('statut', $filters['statut']);
        }

        if (! empty($filters['sender_id'])) {
            $query->where('sender_id', (int) $filters['sender_id']);
        }

        if (! empty($filters['destinataire'])) {
            $query->where('destinataire', 'like', '%'.$filters['destinataire'].'%');
        }

        if (! empty($filters['date_debut']) || ! empty($filters['date_fin'])) {
            $dateDebut = ! empty($filters['date_debut'])
                ? Carbon::createFromFormat('Y-m-d', $filters['date_debut'])->startOfDay()
                : Carbon::create(1970, 1, 1)->startOfDay();

            $dateFin = ! empty($filters['date_fin'])
                ? Carbon::createFromFormat('Y-m-d', $filters['date_fin'])->endOfDay()
                : Carbon::now()->endOfDay();

            $query->whereBetween('created_at', [$dateDebut, $dateFin]);
        }

        $logs = $query
            ->orderByDesc('created_at')
            ->paginate($perPage);

        return response()->json([
            'data' => $logs->getCollection()->map(fn (SmsLog $log): array => [
                'id' => $log->id,
                'destinataire' => $log->destinataire,
                'message' => $log->message,
                'statut' => $log->statut,
                'cout' => (float) $log->cout,
                'sender' => [
                    'nom' => $log->sender->nom ?? null,
                ],
                'created_at' => $log->created_at?->toISOString(),
            ]),
            'meta' => [
                'current_page' => $logs->currentPage(),
                'total' => $logs->total(),
                'per_page' => $logs->perPage(),
                'last_page' => $logs->lastPage(),
            ],
        ], 200);
    }
}
