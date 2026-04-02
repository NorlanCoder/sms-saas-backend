<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\SmsLog;
use App\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function stats(Request $request): JsonResponse
    {
        /** @var Company|null $company */
        $company = $request->user();

        if (! $company) {
            return response()->json([
                'message' => 'Non authentifie',
            ], 401);
        }

        $validated = $request->validate([
            'periode' => ['nullable', 'in:aujourd_hui,semaine,mois'],
        ]);

        $periode = $validated['periode'] ?? 'mois';
        $now = Carbon::now();

        [$dateDebut, $dateFin] = match ($periode) {
            'aujourd_hui' => [Carbon::today(), $now],
            'semaine' => [$now->copy()->startOfWeek(), $now],
            default => [$now->copy()->startOfMonth(), $now],
        };

        $smsStats = SmsLog::query()
            ->where('company_id', $company->id)
            ->whereBetween('created_at', [$dateDebut, $dateFin])
            ->selectRaw('COUNT(*) as total_sms')
            ->selectRaw("SUM(CASE WHEN statut = 'envoye' THEN 1 ELSE 0 END) as sms_envoyes")
            ->selectRaw("SUM(CASE WHEN statut = 'echoue' THEN 1 ELSE 0 END) as sms_echoues")
            ->selectRaw("SUM(CASE WHEN statut = 'en_attente' THEN 1 ELSE 0 END) as sms_en_attente")
            ->selectRaw('COALESCE(SUM(cout), 0) as depenses_totales')
            ->first();

        $rechargesTotales = Transaction::query()
            ->where('company_id', $company->id)
            ->where('type', 'recharge')
            ->whereBetween('created_at', [$dateDebut, $dateFin])
            ->sum('montant');

        return response()->json([
            'periode' => $periode,
            'date_debut' => $dateDebut->toDateString(),
            'date_fin' => $dateFin->toDateString(),
            'stats' => [
                'total_sms' => (int) ($smsStats->total_sms ?? 0),
                'sms_envoyes' => (int) ($smsStats->sms_envoyes ?? 0),
                'sms_echoues' => (int) ($smsStats->sms_echoues ?? 0),
                'sms_en_attente' => (int) ($smsStats->sms_en_attente ?? 0),
                'depenses_totales' => round((float) ($smsStats->depenses_totales ?? 0), 4),
                'solde_actuel' => round((float) $company->solde, 2),
                'recharges_totales' => round((float) $rechargesTotales, 2),
            ],
        ], 200);
    }
}
