<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SmsLog;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminSmsLogController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'company_id' => ['nullable', 'integer'],
            'statut' => ['nullable', 'in:envoye,echoue,en_attente'],
            'date_debut' => ['nullable', 'date_format:Y-m-d'],
            'date_fin' => ['nullable', 'date_format:Y-m-d'],
        ]);

        $query = SmsLog::query()
            ->with(['company:id,nom', 'sender:id,nom'])
            ->orderByDesc('created_at');

        if (! empty($filters['company_id'])) {
            $query->where('company_id', (int) $filters['company_id']);
        }

        if (! empty($filters['statut'])) {
            $query->where('statut', $filters['statut']);
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

        $smsLogs = $query->paginate(20);

        return response()->json($smsLogs, 200);
    }
}
