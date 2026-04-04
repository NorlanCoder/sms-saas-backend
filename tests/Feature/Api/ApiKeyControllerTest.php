<?php

namespace Tests\Feature\Api;

use App\Models\ApiKey;
use App\Models\Company;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ApiKeyControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_requires_authentication(): void
    {
        $this->getJson('/api/v1/keys')
            ->assertStatus(401);
    }

    public function test_index_returns_404_when_no_active_key_exists(): void
    {
        $company = Company::factory()->create();
        Sanctum::actingAs($company);

        ApiKey::query()->create([
            'company_id' => $company->id,
            'public_key' => 'old-key',
            'statut' => 'révoquée',
        ]);

        $this->getJson('/api/v1/keys')
            ->assertStatus(404)
            ->assertJson([
                'message' => 'Aucune clé active trouvée',
            ]);
    }

    public function test_index_returns_latest_active_key_for_authenticated_company(): void
    {
        $company = Company::factory()->create();
        $otherCompany = Company::factory()->create();

        $oldKey = ApiKey::query()->create([
            'company_id' => $company->id,
            'public_key' => 'active-key-old',
            'statut' => 'active',
        ]);

        $latestKey = ApiKey::query()->create([
            'company_id' => $company->id,
            'public_key' => 'active-key-latest',
            'statut' => 'active',
        ]);

        ApiKey::query()->whereKey($oldKey->id)->update([
            'created_at' => now()->subHour(),
        ]);

        ApiKey::query()->whereKey($latestKey->id)->update([
            'created_at' => now(),
        ]);

        ApiKey::query()->create([
            'company_id' => $otherCompany->id,
            'public_key' => 'other-company-key',
            'statut' => 'active',
        ]);

        Sanctum::actingAs($company);

        $this->getJson('/api/v1/keys')
            ->assertOk()
            ->assertJsonPath('public_key', 'active-key-latest')
            ->assertJsonPath('statut', 'active');
    }

    public function test_regenerate_requires_authentication(): void
    {
        $this->postJson('/api/v1/keys/regenerate')
            ->assertStatus(401);
    }

    public function test_regenerate_revokes_previous_keys_and_creates_new_active_key(): void
    {
        $company = Company::factory()->create();

        ApiKey::query()->create([
            'company_id' => $company->id,
            'public_key' => 'existing-active-key',
            'statut' => 'active',
            'created_at' => now()->subDay(),
        ]);

        Sanctum::actingAs($company);

        $response = $this->postJson('/api/v1/keys/regenerate');

        $response
            ->assertOk()
            ->assertJsonPath('message', 'Clés régénérées avec succès')
            ->assertJsonPath('public_key', fn (string $value): bool => str_starts_with($value, '-----BEGIN PUBLIC KEY-----'))
            ->assertJsonPath('private_key', fn (string $value): bool => str_starts_with($value, '-----BEGIN PRIVATE KEY-----'));

        $this->assertDatabaseHas('api_keys', [
            'company_id' => $company->id,
            'public_key' => 'existing-active-key',
            'statut' => 'révoquée',
        ]);

        $newPublicKey = (string) $response->json('public_key');

        $this->assertDatabaseHas('api_keys', [
            'company_id' => $company->id,
            'public_key' => $newPublicKey,
            'statut' => 'active',
        ]);

        $this->assertSame(1, ApiKey::query()->where('company_id', $company->id)->where('statut', 'active')->count());
    }

    public function test_regenerate_does_not_revoke_keys_of_other_companies(): void
    {
        $company = Company::factory()->create();
        $otherCompany = Company::factory()->create();

        ApiKey::query()->create([
            'company_id' => $company->id,
            'public_key' => 'company-active-key',
            'statut' => 'active',
        ]);

        ApiKey::query()->create([
            'company_id' => $otherCompany->id,
            'public_key' => 'other-active-key',
            'statut' => 'active',
        ]);

        Sanctum::actingAs($company);

        $this->postJson('/api/v1/keys/regenerate')->assertOk();

        $this->assertDatabaseHas('api_keys', [
            'company_id' => $otherCompany->id,
            'public_key' => 'other-active-key',
            'statut' => 'active',
        ]);
    }
}
