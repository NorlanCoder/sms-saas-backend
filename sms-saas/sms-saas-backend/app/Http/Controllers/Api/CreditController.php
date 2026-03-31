<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\InsufficientBalanceException;
use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Transaction;
use App\Services\CreditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CreditController extends Controller
{
    public function __construct(private readonly CreditService $creditService)
    {
    }

    public function balance(Request $request): JsonResponse
    {
        /** @var Company|null $company */
        $company = $request->user();

        if (! $company) {
            return response()->json([
                'message' => 'Non authentifie',
            ], 401);
        }

        return response()->json([
            'solde' => round($this->creditService->getBalance((int) $company->id), 2),
            'currency' => 'XOF',
        ], 200);
    }

    public function recharge(Request $request): JsonResponse
    {
        /** @var Company|null $company */
        $company = $request->user();

        if (! $company) {
            return response()->json([
                'message' => 'Non authentifie',
            ], 401);
        }

        $validated = $request->validate([
            'montant' => ['required', 'numeric', 'min:1'],
            'methode' => ['required', 'in:mobile_money,carte_bancaire'],
            'phone' => ['required_if:methode,mobile_money', 'string'],
        ]);

        $amount = (float) $validated['montant'];
        $description = 'Recharge via '.$validated['methode'];

        try {
            // Stub temporaire: paiement considéré confirmé.
            $transaction = $this->creditService->addCredit((int) $company->id, $amount, $description);

            return response()->json([
                'message' => 'Rechargement effectué avec succès',
                'solde' => round($this->creditService->getBalance((int) $company->id), 2),
                'transaction' => [
                    'id' => $transaction->id,
                    'type' => $transaction->type,
                    'montant' => $transaction->montant,
                    'description' => $transaction->description,
                    'created_at' => $transaction->created_at,
                ],
            ], 200);
        } catch (InsufficientBalanceException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 402);
        }
    }

    public function transactions(Request $request): JsonResponse
    {
        /** @var Company|null $company */
        $company = $request->user();

        if (! $company) {
            return response()->json([
                'message' => 'Non authentifie',
            ], 401);
        }

        $filters = $request->validate([
            'type' => ['nullable', 'in:recharge,debit'],
            'date_debut' => ['nullable', 'date_format:Y-m-d'],
            'date_fin' => ['nullable', 'date_format:Y-m-d'],
        ]);

        $query = Transaction::query()
            ->where('company_id', $company->id)
            ->orderByDesc('created_at');

        if (! empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        if (! empty($filters['date_debut'])) {
            $query->whereDate('created_at', '>=', $filters['date_debut']);
        }

        if (! empty($filters['date_fin'])) {
            $query->whereDate('created_at', '<=', $filters['date_fin']);
        }

        $transactions = $query->paginate(15);

        return response()->json($transactions, 200);
    }
}
