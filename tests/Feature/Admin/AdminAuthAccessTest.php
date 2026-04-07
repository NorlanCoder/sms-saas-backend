<?php

namespace Tests\Feature\Admin;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminAuthAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_login_returns_token_and_admin_payload(): void
    {
        User::query()->create([
            'name' => 'Admin',
            'email' => 'admin@sms-saas.com',
            'password' => Hash::make('Admin@1234'),
        ]);

        $response = $this->postJson('/api/admin/login', [
            'email' => 'admin@sms-saas.com',
            'password' => 'Admin@1234',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('message', 'Connexion admin réussie')
            ->assertJsonPath('admin.email', 'admin@sms-saas.com')
            ->assertJsonPath('admin.role', 'admin')
            ->assertJsonStructure([
                'token',
                'admin' => ['id', 'name', 'email', 'role'],
            ]);
    }

    public function test_admin_login_returns_403_for_super_admin_role(): void
    {
        User::query()->create([
            'name' => 'Super Admin',
            'email' => 'superadmin@sms-saas.com',
            'password' => Hash::make('SuperAdmin@1234'),
            'role' => 'super_admin',
        ]);

        $response = $this->postJson('/api/admin/login', [
            'email' => 'superadmin@sms-saas.com',
            'password' => 'SuperAdmin@1234',
        ]);

        $response
            ->assertStatus(403)
            ->assertJsonPath('message', 'Accès admin requis');
    }

    public function test_admin_login_returns_401_for_invalid_credentials(): void
    {
        User::query()->create([
            'name' => 'Admin',
            'email' => 'admin@sms-saas.com',
            'password' => Hash::make('Admin@1234'),
        ]);

        $response = $this->postJson('/api/admin/login', [
            'email' => 'admin@sms-saas.com',
            'password' => 'wrong-password',
        ]);

        $response
            ->assertStatus(401)
            ->assertJsonPath('message', 'Identifiants incorrects');
    }

    public function test_admin_logout_revokes_current_token(): void
    {
        $admin = User::query()->create([
            'name' => 'Admin',
            'email' => 'admin@sms-saas.com',
            'password' => Hash::make('Admin@1234'),
        ]);

        $token = $admin->createToken('admin-token', ['admin'])->plainTextToken;

        $response = $this
            ->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/admin/logout');

        $response
            ->assertOk()
            ->assertJsonPath('message', 'Déconnexion réussie');

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_admin_protected_routes_require_authentication(): void
    {
        $this->getJson('/api/admin/companies')->assertStatus(401);
    }

    public function test_company_token_is_forbidden_on_admin_routes(): void
    {
        $company = $this->createCompany('company-access@example.test');

        Sanctum::actingAs($company);

        $this->getJson('/api/admin/companies')
            ->assertStatus(403)
            ->assertJsonPath('message', 'Accès refusé');
    }

    public function test_user_without_admin_ability_is_forbidden_on_admin_routes(): void
    {
        $user = User::query()->create([
            'name' => 'Support',
            'email' => 'support@example.test',
            'password' => Hash::make('Password@123'),
        ]);

        Sanctum::actingAs($user, ['company']);

        $this->getJson('/api/admin/companies')
            ->assertStatus(403)
            ->assertJsonPath('message', 'Accès refusé');
    }

    public function test_super_admin_user_is_forbidden_on_admin_routes(): void
    {
        $superAdmin = User::query()->create([
            'name' => 'Super Admin',
            'email' => 'super-admin-admin-route@example.test',
            'password' => Hash::make('SuperAdmin@1234'),
            'role' => 'super_admin',
        ]);

        Sanctum::actingAs($superAdmin, ['admin', 'super-admin']);

        $this->getJson('/api/admin/companies')
            ->assertStatus(403)
            ->assertJsonPath('message', 'Accès admin requis');
    }

    private function createCompany(string $email): Company
    {
        $company = new Company([
            'nom' => 'Company Access',
            'email' => $email,
            'pays' => 'BJ',
            'telephone' => '+22996000000',
            'solde' => 100,
            'statut' => 'actif',
        ]);

        $company->password = 'secret';
        $company->save();

        return $company;
    }
}
