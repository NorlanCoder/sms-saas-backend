<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Transaction;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Transaction>
 */
class TransactionFactory extends Factory
{
    protected $model = Transaction::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'type' => fake()->randomElement(['recharge', 'debit']),
            'montant' => fake()->randomFloat(2, 10, 500),
            'description' => fake()->sentence(),
        ];
    }
}
