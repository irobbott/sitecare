<?php

namespace Tests\Feature;

use App\Models\Organisation;
use App\Models\Ticket;
use App\Models\User;
use App\Models\Website;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TicketAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_administrators_can_change_ticket_priority(): void
    {
        $organisation = Organisation::create(['name' => 'Northstar', 'slug' => 'northstar', 'contact_email' => 'ops@example.test']);
        $client = User::create(['name' => 'Client', 'email' => 'client@example.test', 'password' => 'password', 'role' => 'client', 'organisation_id' => $organisation->id]);
        $technician = User::create(['name' => 'Tech', 'email' => 'tech@example.test', 'password' => 'password', 'role' => 'technician']);
        $admin = User::create(['name' => 'Admin', 'email' => 'admin@example.test', 'password' => 'password', 'role' => 'admin']);
        $website = Website::create(['organisation_id' => $organisation->id, 'technician_id' => $technician->id, 'name' => 'Northstar', 'url' => 'https://example.test', 'status' => 'active']);
        $ticket = Ticket::create(['number' => 'SC-2026-00001', 'organisation_id' => $organisation->id, 'website_id' => $website->id, 'reporter_id' => $client->id, 'assignee_id' => $technician->id, 'subject' => 'Support request', 'description' => 'Details', 'category' => 'Other', 'priority' => 'normal', 'status' => 'assigned']);

        $this->actingAs($client)->patchJson("/api/v1/tickets/{$ticket->id}", ['priority' => 'urgent'])->assertForbidden();
        $this->actingAs($technician)->patchJson("/api/v1/tickets/{$ticket->id}", ['priority' => 'urgent'])->assertForbidden();
        $this->actingAs($admin)->patchJson("/api/v1/tickets/{$ticket->id}", ['priority' => 'urgent'])->assertOk();
        $this->assertDatabaseHas('tickets', ['id' => $ticket->id, 'priority' => 'urgent']);
    }
}
