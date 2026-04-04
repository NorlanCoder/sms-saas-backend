<?php

namespace Tests\Feature\Api;

use App\Models\Company;
use App\Models\SenderID;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SenderIdControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_requires_authentication(): void
    {
        $this->getJson('/api/v1/sender-ids')
            ->assertStatus(401);
    }

    public function test_index_returns_only_current_company_sender_ids_ordered_by_created_at_desc(): void
    {
        $company = Company::factory()->create();
        $otherCompany = Company::factory()->create();

        $older = SenderID::query()->create([
            'company_id' => $company->id,
            'nom' => 'OLD123',
            'statut' => 'en_attente',
        ]);

        $newer = SenderID::query()->create([
            'company_id' => $company->id,
            'nom' => 'NEW456',
            'statut' => 'rejete',
        ]);

        SenderID::query()->whereKey($older->id)->update([
            'created_at' => now()->subHour(),
        ]);

        SenderID::query()->whereKey($newer->id)->update([
            'created_at' => now(),
        ]);

        SenderID::query()->create([
            'company_id' => $otherCompany->id,
            'nom' => 'OTHER999',
            'statut' => 'valide',
        ]);

        Sanctum::actingAs($company);

        $this->getJson('/api/v1/sender-ids')
            ->assertOk()
            ->assertJsonCount(2, 'sender_ids')
            ->assertJsonPath('sender_ids.0.id', $newer->id)
            ->assertJsonPath('sender_ids.1.id', $older->id)
            ->assertJsonPath('sender_ids.0.nom', 'NEW456');
    }

    public function test_store_requires_authentication(): void
    {
        $this->postJson('/api/v1/sender-ids', [
            'nom' => 'BANQUEXYZ',
        ])->assertStatus(401);
    }

    public function test_store_returns_422_for_invalid_sender_id_name(): void
    {
        $company = Company::factory()->create();
        Sanctum::actingAs($company);

        $this->postJson('/api/v1/sender-ids', [
            'nom' => 'ABC DEF',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['nom']);
    }

    public function test_store_creates_sender_id_in_uppercase_with_pending_status(): void
    {
        $company = Company::factory()->create();
        Sanctum::actingAs($company);

        $response = $this->postJson('/api/v1/sender-ids', [
            'nom' => 'banquexyz',
        ]);

        $response
            ->assertStatus(201)
            ->assertJsonPath('sender_id.nom', 'BANQUEXYZ')
            ->assertJsonPath('sender_id.statut', 'en_attente');

        $this->assertDatabaseHas('sender_ids', [
            'company_id' => $company->id,
            'nom' => 'BANQUEXYZ',
            'statut' => 'en_attente',
        ]);
    }

    public function test_store_returns_422_when_sender_id_already_exists_for_company(): void
    {
        $company = Company::factory()->create();

        SenderID::query()->create([
            'company_id' => $company->id,
            'nom' => 'BANQUEXYZ',
            'statut' => 'en_attente',
        ]);

        Sanctum::actingAs($company);

        $this->postJson('/api/v1/sender-ids', [
            'nom' => 'banquexyz',
        ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Vous avez déjà un SENDER_ID avec ce nom');
    }

    public function test_show_returns_sender_id_for_owner_only(): void
    {
        $company = Company::factory()->create();
        $otherCompany = Company::factory()->create();

        $owned = SenderID::query()->create([
            'company_id' => $company->id,
            'nom' => 'MYBRAND1',
            'statut' => 'valide',
        ]);

        $other = SenderID::query()->create([
            'company_id' => $otherCompany->id,
            'nom' => 'OTHERBRD',
            'statut' => 'valide',
        ]);

        Sanctum::actingAs($company);

        $this->getJson('/api/v1/sender-ids/'.$owned->id)
            ->assertOk()
            ->assertJsonPath('sender_id.id', $owned->id)
            ->assertJsonPath('sender_id.nom', 'MYBRAND1');

        $this->getJson('/api/v1/sender-ids/'.$other->id)
            ->assertStatus(404)
            ->assertJsonPath('message', 'SENDER_ID introuvable');
    }

    public function test_destroy_requires_authentication(): void
    {
        $sender = SenderID::factory()->create();

        $this->deleteJson('/api/v1/sender-ids/'.$sender->id)
            ->assertStatus(401);
    }

    public function test_destroy_allows_pending_or_rejected_sender_id_and_deletes_it(): void
    {
        $company = Company::factory()->create();

        $pending = SenderID::query()->create([
            'company_id' => $company->id,
            'nom' => 'PENDING1',
            'statut' => 'en_attente',
        ]);

        $rejected = SenderID::query()->create([
            'company_id' => $company->id,
            'nom' => 'REJECT11',
            'statut' => 'rejete',
        ]);

        Sanctum::actingAs($company);

        $this->deleteJson('/api/v1/sender-ids/'.$pending->id)
            ->assertOk()
            ->assertJsonPath('message', 'SENDER_ID supprimé avec succès');

        $this->deleteJson('/api/v1/sender-ids/'.$rejected->id)
            ->assertOk()
            ->assertJsonPath('message', 'SENDER_ID supprimé avec succès');

        $this->assertDatabaseMissing('sender_ids', ['id' => $pending->id]);
        $this->assertDatabaseMissing('sender_ids', ['id' => $rejected->id]);
    }

    public function test_destroy_returns_403_for_validated_or_suspended_sender_id(): void
    {
        $company = Company::factory()->create();

        $validated = SenderID::query()->create([
            'company_id' => $company->id,
            'nom' => 'VALID123',
            'statut' => 'valide',
        ]);

        $suspended = SenderID::query()->create([
            'company_id' => $company->id,
            'nom' => 'SUSP1234',
            'statut' => 'suspendu',
        ]);

        Sanctum::actingAs($company);

        $this->deleteJson('/api/v1/sender-ids/'.$validated->id)
            ->assertStatus(403)
            ->assertJsonPath('message', 'Impossible de supprimer un SENDER_ID validé ou suspendu');

        $this->deleteJson('/api/v1/sender-ids/'.$suspended->id)
            ->assertStatus(403)
            ->assertJsonPath('message', 'Impossible de supprimer un SENDER_ID validé ou suspendu');
    }

    public function test_destroy_returns_404_when_sender_id_belongs_to_another_company(): void
    {
        $company = Company::factory()->create();
        $otherCompany = Company::factory()->create();

        $otherSender = SenderID::query()->create([
            'company_id' => $otherCompany->id,
            'nom' => 'OTHERSID',
            'statut' => 'en_attente',
        ]);

        Sanctum::actingAs($company);

        $this->deleteJson('/api/v1/sender-ids/'.$otherSender->id)
            ->assertStatus(404)
            ->assertJsonPath('message', 'SENDER_ID introuvable');
    }
}
