<?php

namespace Tests\Feature;

use App\Models\Organisation;
use App\Models\Ticket;
use App\Models\User;
use App\Models\Website;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class DashboardAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    public function test_ticket_reports_are_scoped_and_count_overdue_first_responses(): void
    {
        Carbon::setTestNow('2026-10-02 12:00:00');
        $organisation = Organisation::create(['name' => 'Northstar', 'slug' => 'northstar', 'contact_email' => 'northstar@example.test']);
        $otherOrganisation = Organisation::create(['name' => 'Other', 'slug' => 'other', 'contact_email' => 'other@example.test']);
        $client = User::create(['name' => 'Client', 'email' => 'client@example.test', 'password' => 'password', 'role' => 'client', 'organisation_id' => $organisation->id]);
        $website = Website::create(['organisation_id' => $organisation->id, 'name' => 'Northstar', 'url' => 'https://example.test', 'status' => 'active']);
        $otherWebsite = Website::create(['organisation_id' => $otherOrganisation->id, 'name' => 'Other', 'url' => 'https://other.test', 'status' => 'active']);
        Ticket::create(['number' => 'SC-2026-00001', 'organisation_id' => $organisation->id, 'website_id' => $website->id, 'reporter_id' => $client->id, 'subject' => 'Late reply', 'description' => 'Details', 'category' => 'Other', 'priority' => 'urgent', 'status' => 'open', 'response_due_at' => now()->subHour()]);
        Ticket::create(['number' => 'SC-2026-00002', 'organisation_id' => $otherOrganisation->id, 'website_id' => $otherWebsite->id, 'reporter_id' => $client->id, 'subject' => 'Private ticket', 'description' => 'Details', 'category' => 'Other', 'priority' => 'high', 'status' => 'open', 'response_due_at' => now()->subHour()]);

        $this->actingAs($client)->getJson('/api/v1/dashboard')->assertOk()
            ->assertJsonPath('data.ticket_status_counts.open', 1)
            ->assertJsonPath('data.ticket_priority_counts.urgent', 1)
            ->assertJsonPath('data.response_target_metrics.measured', 1)
            ->assertJsonPath('data.response_target_metrics.overdue', 1)
            ->assertJsonPath('data.response_target_metrics.on_time_percent', 0)
            ->assertJsonPath('data.dashboard_metrics.urgent_tickets', 1)
            ->assertJsonPath('data.dashboard_metrics.overdue_response_tickets', 1)
            ->assertJsonPath('data.dashboard_metrics.active_organisations', null)
            ->assertDontSee('Private ticket');

        $admin = User::create(['name' => 'Admin', 'email' => 'admin@example.test', 'password' => 'password', 'role' => 'admin']);
        $this->actingAs($admin)->getJson('/api/v1/dashboard')->assertOk()
            ->assertJsonPath('data.dashboard_metrics.active_organisations', 2)
            ->assertJsonPath('data.dashboard_metrics.pending_websites', 0);
        Carbon::setTestNow();
    }
}
