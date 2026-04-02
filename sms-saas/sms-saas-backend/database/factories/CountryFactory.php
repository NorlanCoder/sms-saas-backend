<?php

namespace Database\Factories;

use App\Models\Country;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Country>
 */
class CountryFactory extends Factory
{
    protected $model = Country::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nom' => 'Senegal',
            'code_pays' => strtoupper(fake()->unique()->lexify('??')),
            'code_indicatif' => '+221',
            'tarif_sms' => 25.0,
            'statut' => true,
        ];
    }
}
