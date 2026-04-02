<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminTransactionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'company_id' => ['nullable', 'integer'],
            'type' => ['nullable', 'in:recharge,debit'],
            'date_debut' => ['nullable', 'date_format:Y-m-d'],
            'date_fin' => ['nullable', 'date_format:Y-m-d'],
        ]);

        $query = Transaction::query()
            ->with(['company:id,nom'])
            ->orderByDesc('created_at');

        if (! empty($filters['company_id'])) {
            $query->where('company_id', (int) $filters['company_id']);
        }

        if (! empty($filters['type'])) {
            $query->where('type', $filters['type']);
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

        $transactions = $query->paginate(20);

        return response()->json($transactions, 200);
    }
}
