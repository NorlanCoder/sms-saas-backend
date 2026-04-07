<?php

namespace Tests\Feature\Admin;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SuperAdminAuthAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_login_returns_token_and_admin_payload(): void
    {
        User::query()->create([
            'name' => 'Super Admin',
            'email' => 'superadmin@sms-saas.com',
            'password' => Hash::make('SuperAdmin@1234'),
            'role' => 'super_admin',
        ]);

        $response = $this->postJson('/api/super-admin/login', [
            'email' => 'superadmin@sms-saas.com',
            'password' => 'SuperAdmin@1234',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('message', 'Connexion super admin réussie')
            ->assertJsonPath('admin.email', 'superadmin@sms-saas.com')
            ->assertJsonPath('admin.role', 'super_admin')
            ->assertJsonStructure([
                'token',
                'admin' => ['id', 'name', 'email', 'role'],
            ]);
    }

    public function test_super_admin_login_returns_403_for_non_super_admin_role(): void
    {
        User::query()->create([
            'name' => 'Admin',
            'email' => 'admin@sms-saas.com',
            'password' => Hash::make('Admin@1234'),
            'role' => 'admin',
        ]);

        $response = $this->postJson('/api/super-admin/login', [
            'email' => 'admin@sms-saas.com',
            'password' => 'Admin@1234',
        ]);

        $response
            ->assertStatus(403)
            ->assertJsonPath('message', 'Accès super admin requis');
    }

    public function test_super_admin_protected_routes_require_authentication(): void
    {
        $this->getJson('/api/super-admin/companies')->assertStatus(401);
    }

    public function test_admin_user_is_forbidden_on_super_admin_routes(): void
    {
        $admin = User::query()->create([
            'name' => 'Admin',
            'email' => 'admin@example.test',
            'password' => Hash::make('Admin@1234'),
            'role' => 'admin',
        ]);

        Sanctum::actingAs($admin, ['admin']);

        $this->getJson('/api/super-admin/companies')
            ->assertStatus(403)
            ->assertJsonPath('message', 'Accès super admin requis');
    }

    public function test_super_admin_without_super_admin_ability_is_forbidden(): void
    {
        $superAdmin = User::query()->create([
            'name' => 'Super Admin',
            'email' => 'super-admin-no-ability@example.test',
            'password' => Hash::make('Admin@1234'),
            'role' => 'super_admin',
        ]);

        Sanctum::actingAs($superAdmin, ['admin']);

        $this->getJson('/api/super-admin/companies')
            ->assertStatus(403)
            ->assertJsonPath('message', 'Accès refusé');
    }

    public function test_company_token_is_forbidden_on_super_admin_routes(): void
    {
        $company = $this->createCompany('company-super-admin-access@example.test');

        Sanctum::actingAs($company);

        $this->getJson('/api/super-admin/companies')
            ->assertStatus(403)
            ->assertJsonPath('message', 'Accès refusé');
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
