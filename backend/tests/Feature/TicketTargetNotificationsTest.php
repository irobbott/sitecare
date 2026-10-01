<?php

namespace Tests\Feature;

use App\Models\Organisation;
use App\Models\Ticket;
use App\Models\User;
use App\Models\Website;
use App\Notifications\SiteCareAlert;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class TicketTargetNotificationsTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_it_alerts_assigned_staff_once_for_approaching_and_overdue_targets_and_skips_paused_resolution(): void
    {
        Carbon::setTestNow('2026-10-02 12:00:00');
        Notification::fake();
        $organisation = Organisation::create(['name' => 'Northstar', 'slug' => 'northstar', 'contact_email' => 'client@example.test']);
        $client = User::create(['name' => 'Client', 'email' => 'client@example.test', 'password' => 'password', 'role' => 'client', 'organisation_id' => $organisation->id]);
        $technician = User::create(['name' => 'Tech', 'email' => 'tech@example.test', 'password' => 'password', 'role' => 'technician']);
        $website = Website::create(['organisation_id' => $organisation->id, 'name' => 'Northstar site', 'url' => 'https://example.test', 'status' => 'active']);

        $approaching = Ticket::create([
            'number' => 'SC-2026-00001', 'organisation_id' => $organisation->id, 'website_id' => $website->id,
            'reporter_id' => $client->id, 'assignee_id' => $technician->id, 'subject' => 'Response due soon',
            'description' => 'Details', 'category' => 'Other', 'priority' => 'normal', 'status' => 'in_progress',
            'response_due_at' => now()->addMinutes(30), 'resolution_due_at' => now()->addDays(2),
        ]);
        $overdue = Ticket::create([
            'number' => 'SC-2026-00002', 'organisation_id' => $organisation->id, 'website_id' => $website->id,
            'reporter_id' => $client->id, 'assignee_id' => $technician->id, 'subject' => 'Resolution target overdue',
            'description' => 'Details', 'category' => 'Other', 'priority' => 'normal', 'status' => 'in_progress',
            'first_response_at' => now()->subDay(), 'response_due_at' => now()->subHour(), 'resolution_due_at' => now()->subHour(),
        ]);
        $waiting = Ticket::create([
            'number' => 'SC-2026-00003', 'organisation_id' => $organisation->id, 'website_id' => $website->id,
            'reporter_id' => $client->id, 'assignee_id' => $technician->id, 'subject' => 'Waiting for client',
            'description' => 'Details', 'category' => 'Other', 'priority' => 'normal', 'status' => 'waiting_for_client',
            'first_response_at' => now()->subDay(), 'response_due_at' => now()->subHour(),
            'resolution_due_at' => now()->subHour(), 'waiting_since' => now()->subDay(),
        ]);

        Artisan::call('sitecare:notify-ticket-targets');
        Artisan::call('sitecare:notify-ticket-targets');

        Notification::assertSentToTimes($technician, SiteCareAlert::class, 2);
        $this->assertNotNull($approaching->fresh()->response_target_warned_at);
        $this->assertNull($approaching->fresh()->response_target_overdue_at);
        $this->assertNotNull($overdue->fresh()->resolution_target_overdue_at);
        $this->assertNull($overdue->fresh()->response_target_overdue_at);
        $this->assertNull($waiting->fresh()->resolution_target_warned_at);
        $this->assertNull($waiting->fresh()->resolution_target_overdue_at);
    }
}
