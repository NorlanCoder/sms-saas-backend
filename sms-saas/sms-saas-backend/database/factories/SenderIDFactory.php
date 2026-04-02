<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\SenderID;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SenderID>
 */
class SenderIDFactory extends Factory
{
    protected $model = SenderID::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'nom' => strtoupper(fake()->lexify('SENDER???')),
            'statut' => 'en_attente',
        ];
    }
}
