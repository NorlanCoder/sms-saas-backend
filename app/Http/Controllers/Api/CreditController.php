<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Services\CreditService;
use App\Services\FedaPayService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class CreditController extends Controller
{
    public function __construct(
        private CreditService $creditService,
        private FedaPayService $fedaPayService
    ) {}

    /**
     * Retourner le solde actuel de l'entreprise
     */

    public function balance(Request $request): JsonResponse
    {
        $company = $request->user();

        return response()->json([
            'solde' => (float) $company->solde,
            'currency' => 'XOF',
        ]);
    }

    /**
     * Initier un rechargement de crédits via FedaPay
     */
    public function recharge(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'montant' => 'required|numeric|min:1000',
            'methode' => 'required|in:mobile_money,carte_bancaire',
            'phone' => 'required_if:methode,mobile_money|string|regex:/^\+[0-9]{8,15}$/',
        ], [
            'montant.min' => 'Le montant minimum est de 1 000 XOF',
            'phone.required_if' => 'Le numero de telephone est requis pour Mobile Money',
            'phone.regex' => 'Format invalide. Ex: +22996000000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Donnees invalides',
                'errors' => $validator->errors(),
            ], 422);
        }

        $company = $request->user();
        $montant = (float) $request->montant;
        $methode = (string) $request->methode;
        $phone = $request->phone;

        $transaction = Transaction::create([
            'company_id' => $company->id,
            'type' => 'recharge',
            'montant' => $montant,
            'description' => 'Rechargement via '.($methode === 'mobile_money' ? 'Mobile Money' : 'Carte bancaire'),
            'payment_status' => 'pending',
            'payment_method' => $methode,
            'phone' => $phone,
        ]);

        if ($methode === 'mobile_money') {
            $result = $this->fedaPayService->initiateTransaction(
                amount: $montant,
                phone: (string) $phone,
                description: "Rechargement SMSSaas - {$company->nom}",
                companyId: $company->id
            );

            if (! $result['success']) {
                $transaction->update(['payment_status' => 'declined']);

                return response()->json([
                    'message' => $result['message'],
                ], 422);
            }

            $transaction->update([
                'fedapay_transaction_id' => (string) $result['transaction_id'],
            ]);

            return response()->json([
                'message' => $result['message'],
                'transaction_id' => $transaction->id,
                'fedapay_transaction_id' => $result['transaction_id'],
                'status' => 'pending',
                'instructions' => 'Verifiez votre telephone et confirmez le paiement de '.number_format($montant, 0, ',', ' ').' XOF.',
            ], 202);
        }

        $transaction->update(['payment_status' => 'pending']);

        return response()->json([
            'message' => 'Paiement par carte bancaire en cours d\'integration.',
            'transaction_id' => $transaction->id,
        ], 202);
    }

    /**
     * Vérifier le statut d'une transaction en attente
     */
    public function checkRechargeStatus(Request $request, int $transactionId): JsonResponse
    {
        $company = $request->user();

        $transaction = Transaction::where('id', $transactionId)
            ->where('company_id', $company->id)
            ->first();

        if (! $transaction) {
            return response()->json(['message' => 'Transaction introuvable'], 404);
        }

        if ($transaction->payment_status === 'approved') {
            return response()->json([
                'status' => 'approved',
                'message' => 'Paiement confirme',
                'solde' => (float) $company->fresh()->solde,
            ]);
        }

        if ($transaction->fedapay_transaction_id) {
            $result = $this->fedaPayService->checkTransactionStatus(
                (int) $transaction->fedapay_transaction_id
            );

            if ($result['success'] && ($result['status'] ?? null) === 'approved') {
                $this->creditService->addCredit(
                    companyId: $company->id,
                    amount: (float) $transaction->montant,
                    description: (string) $transaction->description
                );

                $transaction->update(['payment_status' => 'approved']);

                return response()->json([
                    'status' => 'approved',
                    'message' => 'Paiement confirme ! Votre solde a ete credite.',
                    'montant' => $transaction->montant,
                    'solde' => (float) $company->fresh()->solde,
                ]);
            }

            $transaction->update(['payment_status' => $result['status'] ?? 'pending']);
        }

        return response()->json([
            'status' => $transaction->payment_status,
            'message' => 'Paiement en attente de confirmation.',
        ]);
    }

    /**
     * Webhook FedaPay — reçoit les notifications de paiement
     * Cette route est appelée par FedaPay après confirmation du paiement
     */
    public function webhook(Request $request): JsonResponse
    {
        if (! $this->isValidWebhookSignature($request)) {
            Log::warning('FedaPay webhook : signature invalide', [
                'ip' => $request->ip(),
            ]);

            return response()->json(['message' => 'Signature webhook invalide'], 401);
        }

        Log::info('FedaPay webhook recu', $request->all());

        $event = $request->input('name');
        $transactionData = $request->input('data.transaction');

        if (! $event || ! $transactionData) {
            Log::warning('FedaPay webhook : donnees manquantes');
            return response()->json(['message' => 'Donnees manquantes'], 400);
        }

        $transaction = Transaction::where('fedapay_transaction_id', $transactionData['id'])
            ->where('payment_status', 'pending')
            ->first();

        if (! $transaction) {
            Log::warning('FedaPay webhook : transaction introuvable', [
                'fedapay_id' => $transactionData['id'],
            ]);
            return response()->json(['message' => 'Transaction introuvable'], 404);
        }

        if ($event === 'transaction.approved') {
            $this->creditService->addCredit(
                companyId: $transaction->company_id,
                amount: (float) $transaction->montant,
                description: (string) $transaction->description
            );

            $transaction->update(['payment_status' => 'approved']);

            Log::info('FedaPay webhook : solde credite', [
                'company_id' => $transaction->company_id,
                'montant' => $transaction->montant,
            ]);
        } elseif ($event === 'transaction.declined') {
            $transaction->update(['payment_status' => 'declined']);
            Log::info('FedaPay webhook : paiement refuse', [
                'company_id' => $transaction->company_id,
            ]);
        } elseif ($event === 'transaction.canceled') {
            $transaction->update(['payment_status' => 'canceled']);
            Log::info('FedaPay webhook : paiement annule', [
                'company_id' => $transaction->company_id,
            ]);
        }

        return response()->json(['message' => 'Webhook traite avec succes']);
    }

    private function isValidWebhookSignature(Request $request): bool
    {
        $secret = trim((string) config('fedapay.webhook_secret', ''));

        if ($secret === '') {
            return app()->environment(['local', 'testing']);
        }

        $receivedSignature = $this->getWebhookSignature($request);

        if ($receivedSignature === null) {
            return false;
        }

        $payload = $request->getContent();
        $computedSignature = hash_hmac('sha256', $payload, $secret);

        return hash_equals($computedSignature, $receivedSignature);
    }

    private function getWebhookSignature(Request $request): ?string
    {
        $headerCandidates = [
            'X-FEDAPAY-SIGNATURE',
            'X-FEDAPAY-SIGNATURE-SHA256',
            'X-SIGNATURE',
        ];

        foreach ($headerCandidates as $headerName) {
            $value = $request->header($headerName);

            if (! is_string($value) || trim($value) === '') {
                continue;
            }

            $normalized = trim($value);
            if (str_starts_with($normalized, 'sha256=')) {
                $normalized = substr($normalized, 7);
            }

            return strtolower($normalized);
        }

        return null;
    }

    /**
     * Historique des transactions
     */
    public function transactions(Request $request): JsonResponse
    {
        $company = $request->user();

        $query = Transaction::where('company_id', $company->id)
            ->orderBy('created_at', 'desc');

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        if ($request->filled('date_debut')) {
            $query->whereDate('created_at', '>=', $request->date_debut);
        }

        if ($request->filled('date_fin')) {
            $query->whereDate('created_at', '<=', $request->date_fin);
        }

        $transactions = $query->paginate(15);

        return response()->json($transactions);
    }
}
