<?php

namespace Tests\Feature;

use App\Models\Organisation;
use App\Models\TicketCategory;
use App\Models\User;
use App\Models\Website;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class PlatformSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_configures_response_targets_and_new_tickets_use_them(): void
    {
        Carbon::setTestNow('2026-10-02 12:00:00');
        $admin = User::create(['name' => 'Admin', 'email' => 'admin@example.test', 'password' => 'password', 'role' => 'admin']);
        $organisation = Organisation::create(['name' => 'Northstar', 'slug' => 'northstar', 'contact_email' => 'ops@example.test']);
        $client = User::create(['name' => 'Client', 'email' => 'client@example.test', 'password' => 'password', 'role' => 'client', 'organisation_id' => $organisation->id]);
        $website = Website::create(['organisation_id' => $organisation->id, 'name' => 'Northstar', 'url' => 'https://example.test', 'status' => 'active']);
        TicketCategory::create(['name' => 'Website unavailable']);

        $targets = ['low' => 100, 'normal' => 30, 'high' => 5, 'urgent' => 2];
        $this->actingAs($admin)->patchJson('/api/v1/platform-settings', ['ticket_response_targets' => $targets])
            ->assertOk()->assertJsonPath('data.ticket_response_targets.urgent', 2);
        $this->actingAs($client)->patchJson('/api/v1/platform-settings', ['ticket_response_targets' => $targets])->assertForbidden();
        $this->actingAs($client)->postJson('/api/v1/tickets', [
            'website_id' => $website->id,
            'subject' => 'Site is unavailable',
            'description' => 'The home page returns an error.',
            'category' => 'Website unavailable',
            'priority' => 'urgent',
        ])->assertCreated();

        $this->assertDatabaseHas('tickets', ['priority' => 'urgent', 'response_due_at' => '2026-10-02 14:00:00']);
        Carbon::setTestNow();
    }

    public function test_invalid_response_target_values_are_rejected(): void
    {
        $admin = User::create(['name' => 'Admin', 'email' => 'admin@example.test', 'password' => 'password', 'role' => 'admin']);
        $this->actingAs($admin)->patchJson('/api/v1/platform-settings', [
            'ticket_response_targets' => ['low' => 721, 'normal' => 24, 'high' => 4, 'urgent' => 1],
        ])->assertUnprocessable();
    }
}
