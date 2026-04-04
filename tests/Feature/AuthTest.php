<?php

namespace Tests\Feature;

use App\Models\Company;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_company_can_register(): void
    {
        $payload = [
            'nom' => 'Acme SARL',
            'email' => 'acme@example.com',
            'pays' => 'SN',
            'telephone' => '+221770000001',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ];

        $response = $this->postJson('/api/v1/register', $payload);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'token',
                'private_key',
                'company',
            ]);

        $company = Company::query()->where('email', 'acme@example.com')->first();

        $this->assertNotNull($company);
        $this->assertDatabaseHas('api_keys', [
            'company_id' => $company?->id,
            'statut' => 'active',
        ]);
    }

    public function test_register_fails_with_duplicate_email(): void
    {
        Company::factory()->create(['email' => 'duplicate@example.com']);

        $response = $this->postJson('/api/v1/register', [
            'nom' => 'Another Co',
            'email' => 'duplicate@example.com',
            'pays' => 'SN',
            'telephone' => '+221770000002',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertStatus(422);
    }

    public function test_company_can_login(): void
    {
        Company::factory()->create([
            'email' => 'login@example.com',
            'password' => Hash::make('secret1234'),
            'statut' => 'actif',
        ]);

        $response = $this->postJson('/api/v1/login', [
            'email' => 'login@example.com',
            'password' => 'secret1234',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure(['token']);
    }

    public function test_login_fails_with_wrong_password(): void
    {
        Company::factory()->create([
            'email' => 'wrongpass@example.com',
            'password' => Hash::make('secret1234'),
        ]);

        $response = $this->postJson('/api/v1/login', [
            'email' => 'wrongpass@example.com',
            'password' => 'bad-password',
        ]);

        $response->assertStatus(401);
    }

    public function test_suspended_company_cannot_login(): void
    {
        Company::factory()->create([
            'email' => 'suspended@example.com',
            'password' => Hash::make('secret1234'),
            'statut' => 'suspendu',
        ]);

        $response = $this->postJson('/api/v1/login', [
            'email' => 'suspended@example.com',
            'password' => 'secret1234',
        ]);

        $response->assertStatus(403);
    }

    public function test_company_can_logout(): void
    {
        $company = Company::factory()->create();
        $token = $company->createToken('test-token')->plainTextToken;

        $response = $this->postJson('/api/v1/logout', [], [
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/json',
        ]);

        $response->assertStatus(200);
    }

    public function test_profile_requires_authentication(): void
    {
        $response = $this->getJson('/api/v1/profile');

        $response->assertStatus(401);
    }

    public function test_company_can_update_password(): void
    {
        $company = Company::factory()->create([
            'password' => Hash::make('old-password-123'),
        ]);

        $tokenA = $company->createToken('token-a')->plainTextToken;
        $company->createToken('token-b');

        $response = $this->putJson('/api/v1/profile/password', [
            'current_password' => 'old-password-123',
            'password' => 'new-password-456',
            'password_confirmation' => 'new-password-456',
        ], [
            'Authorization' => 'Bearer '.$tokenA,
            'Accept' => 'application/json',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Mot de passe mis à jour avec succès',
            ]);

        $company->refresh();

        $this->assertTrue(Hash::check('new-password-456', $company->password));
        $this->assertFalse(Hash::check('old-password-123', $company->password));
        $this->assertSame(1, $company->tokens()->count());
    }

    public function test_update_password_fails_with_wrong_current_password(): void
    {
        $company = Company::factory()->create([
            'password' => Hash::make('old-password-123'),
        ]);

        $token = $company->createToken('token-main')->plainTextToken;

        $response = $this->putJson('/api/v1/profile/password', [
            'current_password' => 'invalid-password',
            'password' => 'new-password-456',
            'password_confirmation' => 'new-password-456',
        ], [
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/json',
        ]);

        $response->assertStatus(401)
            ->assertJsonValidationErrors(['current_password']);
    }

    public function test_company_can_logout_all_devices(): void
    {
        $company = Company::factory()->create();
        $token = $company->createToken('token-a')->plainTextToken;
        $company->createToken('token-b');

        $response = $this->postJson('/api/v1/logout-all', [], [
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/json',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Déconnexion de tous les appareils réussie',
            ]);

        $this->assertSame(0, $company->tokens()->count());
    }

    public function test_company_can_delete_account_with_confirmation_name(): void
    {
        $company = Company::factory()->create([
            'nom' => 'SMSSaas Test',
        ]);

        $token = $company->createToken('token-main')->plainTextToken;

        $response = $this->deleteJson('/api/v1/profile', [
            'confirmation_name' => 'SMSSaas Test',
        ], [
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/json',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Compte supprimé avec succès',
            ]);

        $this->assertDatabaseMissing('companies', [
            'id' => $company->id,
        ]);
    }
}
