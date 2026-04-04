<?php

namespace Tests\Feature\Api;

use App\Models\Company;
use App\Models\Country;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CountryControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_is_public_and_returns_only_active_countries(): void
    {
        $activeA = Country::factory()->create([
            'nom' => 'Bénin',
            'code_pays' => 'BEN',
            'code_indicatif' => '+229',
            'tarif_sms' => 1.50,
            'statut' => true,
        ]);

        $activeB = Country::factory()->create([
            'nom' => 'Togo',
            'code_pays' => 'TGO',
            'code_indicatif' => '+228',
            'tarif_sms' => 2.00,
            'statut' => true,
        ]);

        Country::factory()->create([
            'nom' => 'Pays inactif',
            'code_pays' => 'XXX',
            'code_indicatif' => '+999',
            'tarif_sms' => 3.00,
            'statut' => false,
        ]);

        $response = $this->getJson('/api/v1/countries');

        $response
            ->assertOk()
            ->assertJsonCount(2, 'countries')
            ->assertJsonPath('countries.0.nom', 'Bénin')
            ->assertJsonPath('countries.1.nom', 'Togo')
            ->assertJsonPath('countries.0.id', $activeA->id)
            ->assertJsonPath('countries.1.id', $activeB->id);
    }

    public function test_active_requires_authentication(): void
    {
        $this->getJson('/api/v1/countries/active')
            ->assertStatus(401);
    }

    public function test_active_returns_only_activated_countries_for_company(): void
    {
        $company = Company::factory()->create();
        $otherCompany = Company::factory()->create();

        $countryA = Country::factory()->create(['statut' => true, 'nom' => 'Bénin']);
        $countryB = Country::factory()->create(['statut' => true, 'nom' => 'Togo']);
        $countryC = Country::factory()->create(['statut' => true, 'nom' => 'Sénégal']);

        DB::table('company_countries')->insert([
            [
                'company_id' => $company->id,
                'country_id' => $countryA->id,
                'actif' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'company_id' => $company->id,
                'country_id' => $countryB->id,
                'actif' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'company_id' => $otherCompany->id,
                'country_id' => $countryC->id,
                'actif' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        Sanctum::actingAs($company);

        $this->getJson('/api/v1/countries/active')
            ->assertOk()
            ->assertJsonCount(1, 'countries')
            ->assertJsonPath('countries.0.id', $countryA->id)
            ->assertJsonPath('countries.0.actif', true);
    }

    public function test_activate_requires_authentication(): void
    {
        $country = Country::factory()->create(['statut' => true]);

        $this->postJson('/api/v1/countries/activate', [
            'country_id' => $country->id,
        ])->assertStatus(401);
    }

    public function test_activate_validates_country_id_and_creates_or_updates_company_country(): void
    {
        $company = Company::factory()->create();
        $country = Country::factory()->create(['statut' => true]);

        Sanctum::actingAs($company);

        $this->postJson('/api/v1/countries/activate', [
            'country_id' => 999999,
        ])->assertStatus(422)->assertJsonValidationErrors(['country_id']);

        $this->postJson('/api/v1/countries/activate', [
            'country_id' => $country->id,
        ])
            ->assertOk()
            ->assertJsonPath('message', 'Pays activé avec succès')
            ->assertJsonPath('country.id', $country->id);

        $this->assertDatabaseHas('company_countries', [
            'company_id' => $company->id,
            'country_id' => $country->id,
            'actif' => true,
        ]);

        DB::table('company_countries')
            ->where('company_id', $company->id)
            ->where('country_id', $country->id)
            ->update(['actif' => false]);

        $this->postJson('/api/v1/countries/activate', [
            'country_id' => $country->id,
        ])->assertOk();

        $this->assertDatabaseHas('company_countries', [
            'company_id' => $company->id,
            'country_id' => $country->id,
            'actif' => true,
        ]);
    }

    public function test_activate_returns_404_when_country_is_not_available_on_platform(): void
    {
        $company = Company::factory()->create();
        $inactiveCountry = Country::factory()->create(['statut' => false]);

        Sanctum::actingAs($company);

        $this->postJson('/api/v1/countries/activate', [
            'country_id' => $inactiveCountry->id,
        ])
            ->assertStatus(404)
            ->assertJsonPath('message', 'Pays non disponible sur la plateforme');
    }

    public function test_deactivate_requires_authentication(): void
    {
        $country = Country::factory()->create(['statut' => true]);

        $this->postJson('/api/v1/countries/deactivate', [
            'country_id' => $country->id,
        ])->assertStatus(401);
    }

    public function test_deactivate_returns_404_when_country_not_active_for_company(): void
    {
        $company = Company::factory()->create();
        $country = Country::factory()->create(['statut' => true]);

        Sanctum::actingAs($company);

        $this->postJson('/api/v1/countries/deactivate', [
            'country_id' => $country->id,
        ])
            ->assertStatus(404)
            ->assertJsonPath('message', "Ce pays n'est pas activé pour votre compte");
    }

    public function test_deactivate_sets_country_as_inactive_for_company(): void
    {
        $company = Company::factory()->create();
        $country = Country::factory()->create(['statut' => true]);

        DB::table('company_countries')->insert([
            'company_id' => $company->id,
            'country_id' => $country->id,
            'actif' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Sanctum::actingAs($company);

        $this->postJson('/api/v1/countries/deactivate', [
            'country_id' => $country->id,
        ])
            ->assertOk()
            ->assertJsonPath('message', 'Pays désactivé avec succès');

        $this->assertDatabaseHas('company_countries', [
            'company_id' => $company->id,
            'country_id' => $country->id,
            'actif' => false,
        ]);
    }
}
