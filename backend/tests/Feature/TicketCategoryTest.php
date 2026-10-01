<?php

namespace Tests\Feature;

use App\Models\Organisation;
use App\Models\TicketCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TicketCategoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_manage_categories_and_clients_only_see_active_categories(): void
    {
        $admin = User::create(['name' => 'Admin', 'email' => 'admin@example.test', 'password' => 'password', 'role' => 'admin']);
        $organisation = Organisation::create(['name' => 'Northstar', 'slug' => 'northstar', 'contact_email' => 'ops@example.test']);
        $client = User::create(['name' => 'Client', 'email' => 'client@example.test', 'password' => 'password', 'role' => 'client', 'organisation_id' => $organisation->id]);
        $active = TicketCategory::create(['name' => 'Content update']);
        $disabled = TicketCategory::create(['name' => 'Retired category', 'is_active' => false]);

        $this->actingAs($client)->getJson('/api/v1/ticket-categories')->assertOk()
            ->assertJsonCount(1, 'data')->assertJsonPath('data.0.name', 'Content update');
        $this->actingAs($client)->postJson('/api/v1/ticket-categories', ['name' => 'Forbidden'])->assertForbidden();

        $this->actingAs($admin)->postJson('/api/v1/ticket-categories', ['name' => 'Performance'])->assertCreated()
            ->assertJsonPath('data.name', 'Performance');
        $this->patchJson('/api/v1/ticket-categories/'.$active->id, ['is_active' => false])->assertOk()
            ->assertJsonPath('data.is_active', false);
        $this->actingAs($admin)->getJson('/api/v1/ticket-categories')->assertOk()->assertJsonCount(3, 'data');
        $this->assertDatabaseHas('ticket_categories', ['id' => $disabled->id, 'is_active' => false]);
    }
}
