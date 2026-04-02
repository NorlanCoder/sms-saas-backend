<?php

namespace App\Services;

use App\Exceptions\InsufficientBalanceException;
use App\Models\Company;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;

class CreditService
{
    public function getBalance(int $companyId): float
    {
        $balance = Company::query()->whereKey($companyId)->value('solde');

        return (float) ($balance ?? 0);
    }

    public function hasSufficientBalance(int $companyId, float $amount): bool
    {
        return $this->getBalance($companyId) >= $amount;
    }

    public function deductCredit(int $companyId, float $amount, string $description): Transaction
    {
        return DB::transaction(function () use ($companyId, $amount, $description): Transaction {
            if (! $this->hasSufficientBalance($companyId, $amount)) {
                throw new InsufficientBalanceException();
            }

            $updatedRows = DB::table('companies')
                ->where('id', $companyId)
                ->where('solde', '>=', $amount)
                ->decrement('solde', $amount);

            if ($updatedRows === 0) {
                throw new InsufficientBalanceException();
            }

            return Transaction::query()->create([
                'company_id' => $companyId,
                'type' => 'debit',
                'montant' => $amount,
                'description' => $description,
            ]);
        });
    }

    public function addCredit(int $companyId, float $amount, string $description): Transaction
    {
        return DB::transaction(function () use ($companyId, $amount, $description): Transaction {
            DB::table('companies')
                ->where('id', $companyId)
                ->increment('solde', $amount);

            return Transaction::query()->create([
                'company_id' => $companyId,
                'type' => 'recharge',
                'montant' => $amount,
                'description' => $description,
            ]);
        });
    }
}
