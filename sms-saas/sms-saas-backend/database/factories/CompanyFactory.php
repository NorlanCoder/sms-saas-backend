<?php

namespace Database\Factories;

use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * @extends Factory<Company>
 */
class CompanyFactory extends Factory
{
    protected $model = Company::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nom' => fake()->company(),
            'email' => fake()->unique()->safeEmail(),
            'password' => Hash::make('password123'),
            'pays' => 'SN',
            'telephone' => '+221770000000',
            'solde' => 0,
            'statut' => 'actif',
        ];
    }
}
