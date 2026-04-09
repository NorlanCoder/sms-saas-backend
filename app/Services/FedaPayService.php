<?php

namespace App\Services;

use FedaPay\FedaPay;
use FedaPay\Transaction;
use Illuminate\Support\Facades\Log;

class FedaPayService
{
    public function __construct()
    {
        FedaPay::setApiKey(config('fedapay.secret_key'));
        FedaPay::setEnvironment(config('fedapay.environment'));
    }

    /**
     * Initier une transaction de rechargement
     *
     * @param float $amount Montant en XOF
     * @param string $phone Numero de telephone (ex: +22996000000)
     * @param string $description Description de la transaction
     * @param int $companyId ID de l'entreprise (pour le callback)
     * @return array<string, mixed>
     */
    public function initiateTransaction(
        float $amount,
        string $phone,
        string $description,
        int $companyId
    ): array {
        try {
            $transaction = Transaction::create([
                'description' => $description,
                'amount' => (int) $amount,
                'currency' => ['iso' => config('fedapay.currency')],
                'callback_url' => config('fedapay.callback_url'),
                'customer' => [
                    'phone_number' => [
                        'number' => $this->formatPhoneNumber($phone),
                        'country' => $this->getCountryFromPhone($phone),
                    ],
                ],
                'metadata' => [
                    'company_id' => $companyId,
                    'amount' => $amount,
                ],
            ]);

            $transaction->sendNow([
                'phone_number' => [
                    'number' => $this->formatPhoneNumber($phone),
                    'country' => $this->getCountryFromPhone($phone),
                ],
            ]);

            Log::info('FedaPay transaction initiee', [
                'transaction_id' => $transaction->id,
                'company_id' => $companyId,
                'amount' => $amount,
            ]);

            return [
                'success' => true,
                'transaction_id' => $transaction->id,
                'status' => $transaction->status,
                'message' => 'Une demande de paiement a ete envoyee sur votre telephone. Veuillez confirmer.',
            ];
        } catch (\Exception $e) {
            Log::error('FedaPay erreur initiation', [
                'message' => $e->getMessage(),
                'company_id' => $companyId,
                'amount' => $amount,
            ]);

            return [
                'success' => false,
                'message' => 'Impossible d\'initier le paiement : '.$e->getMessage(),
            ];
        }
    }

    /**
     * Verifier le statut d'une transaction
     *
     * @param int $transactionId
     * @return array<string, mixed>
     */
    public function checkTransactionStatus(int $transactionId): array
    {
        try {
            $transaction = Transaction::retrieve($transactionId);

            return [
                'success' => true,
                'transaction_id' => $transaction->id,
                'status' => $transaction->status,
                'amount' => $transaction->amount,
            ];
        } catch (\Exception $e) {
            Log::error('FedaPay erreur verification statut', [
                'transaction_id' => $transactionId,
                'message' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Formater le numero de telephone pour FedaPay
     * FedaPay attend le numero sans l'indicatif pays
     * Ex: +22996000000 -> 96000000
     */
    private function formatPhoneNumber(string $phone): string
    {
        $phone = ltrim($phone, '+');

        $countryCodes = [
            '229' => 'BJ',
            '228' => 'TG',
            '221' => 'SN',
            '225' => 'CI',
            '226' => 'BF',
        ];

        foreach ($countryCodes as $code => $country) {
            if (str_starts_with($phone, $code)) {
                return substr($phone, strlen($code));
            }
        }

        return $phone;
    }

    /**
     * Determiner le pays depuis le numero de telephone
     * FedaPay attend le code pays ISO 2 lettres
     */
    private function getCountryFromPhone(string $phone): string
    {
        $phone = ltrim($phone, '+');

        $countryCodes = [
            '229' => 'BJ',
            '228' => 'TG',
            '221' => 'SN',
            '225' => 'CI',
            '226' => 'BF',
        ];

        foreach ($countryCodes as $code => $country) {
            if (str_starts_with($phone, $code)) {
                return $country;
            }
        }

        return 'BJ';
    }
}
