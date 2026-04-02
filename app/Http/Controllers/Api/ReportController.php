<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\SmsLog;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function export(Request $request): StreamedResponse|JsonResponse
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
        ]);

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
            ->get();

        return response()->streamDownload(function () use ($logs): void {
            $handle = fopen('php://output', 'w');

            if (! $handle) {
                return;
            }

            fputcsv($handle, ['ID', 'Destinataire', 'Message', 'Sender ID', 'Statut', 'Cout', 'Date']);

            foreach ($logs as $log) {
                fputcsv($handle, [
                    $log->id,
                    $log->destinataire,
                    $log->message,
                    $log->sender->nom ?? 'N/A',
                    $log->statut,
                    $log->cout,
                    $log->created_at?->format('Y-m-d H:i:s'),
                ]);
            }

            fclose($handle);
        }, 'sms-export-'.now()->format('Y-m-d').'.csv', [
            'Content-Type' => 'text/csv',
        ]);
    }
}
