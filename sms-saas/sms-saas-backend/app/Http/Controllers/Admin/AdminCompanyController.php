<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Company;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminCompanyController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'statut' => ['nullable', 'in:actif,suspendu'],
            'pays' => ['nullable', 'string'],
            'search' => ['nullable', 'string'],
        ]);

        $query = Company::query()
            ->withCount([
                'smsLogs as sms_envoyes_count' => fn ($smsQuery) => $smsQuery->where('statut', 'envoye'),
            ])
            ->orderByDesc('created_at');

        if (! empty($filters['statut'])) {
            $query->where('statut', $filters['statut']);
        }

        if (! empty($filters['pays'])) {
            $query->where('pays', $filters['pays']);
        }

        if (! empty($filters['search'])) {
            $search = $filters['search'];

            $query->where(function ($searchQuery) use ($search): void {
                $searchQuery->where('nom', 'like', '%'.$search.'%')
                    ->orWhere('email', 'like', '%'.$search.'%');
            });
        }

        $companies = $query->paginate(20);

        return response()->json($companies, 200);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $company = Company::query()
            ->withCount([
                'smsLogs as total_sms_envoyes' => fn ($smsQuery) => $smsQuery->where('statut', 'envoye'),
            ])
            ->withSum('smsLogs as total_depense', 'cout')
            ->with([
                'senderIds:id,company_id,nom,statut,created_at',
                'transactions' => fn ($transactionQuery) => $transactionQuery
                    ->orderByDesc('created_at')
                    ->limit(5),
            ])
            ->find($id);

        if (! $company) {
            return response()->json([
                'message' => 'Company introuvable',
            ], 404);
        }

        return response()->json([
            'company' => [
                'id' => $company->id,
                'nom' => $company->nom,
                'email' => $company->email,
                'pays' => $company->pays,
                'telephone' => $company->telephone,
                'solde' => (float) $company->solde,
                'statut' => $company->statut,
                'total_sms_envoyes' => (int) $company->total_sms_envoyes,
                'total_depense' => round((float) ($company->total_depense ?? 0), 4),
                'sender_ids' => $company->senderIds,
                'dernieres_transactions' => $company->transactions,
                'created_at' => $company->created_at,
            ],
        ], 200);
    }

    public function updateStatus(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'statut' => ['required', 'in:actif,suspendu'],
        ]);

        $company = Company::query()->find($id);

        if (! $company) {
            return response()->json([
                'message' => 'Company introuvable',
            ], 404);
        }

        $company->update([
            'statut' => $validated['statut'],
        ]);

        return response()->json([
            'message' => 'Statut mis à jour avec succès',
            'company' => [
                'id' => $company->id,
                'nom' => $company->nom,
                'email' => $company->email,
                'statut' => $company->statut,
            ],
        ], 200);
    }
}
