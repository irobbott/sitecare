<?php

namespace Tests\Feature;

use App\Models\{BackupRecord,Organisation,User,Website};
use App\Notifications\SiteCareAlert;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class BackupHealthNotificationsTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_overdue_backup_alerts_relevant_users_once_and_a_new_record_resets_the_alert(): void
    {
        Carbon::setTestNow('2026-10-02 12:00:00');
        Notification::fake();
        $organisation = Organisation::create(['name' => 'Northstar', 'slug' => 'northstar', 'contact_email' => 'ops@example.test']);
        $client = User::create(['name' => 'Client', 'email' => 'client@example.test', 'password' => 'password', 'role' => 'client', 'organisation_id' => $organisation->id]);
        $technician = User::create(['name' => 'Tech', 'email' => 'tech@example.test', 'password' => 'password', 'role' => 'technician']);
        $admin = User::create(['name' => 'Admin', 'email' => 'admin@example.test', 'password' => 'password', 'role' => 'admin']);
        $website = Website::create([
            'organisation_id' => $organisation->id, 'technician_id' => $technician->id, 'name' => 'Northstar site',
            'url' => 'https://example.test', 'status' => 'active', 'backup_frequency_hours' => 24,
        ]);
        BackupRecord::create([
            'website_id' => $website->id, 'recorded_by' => $technician->id, 'type' => 'full_site',
            'status' => 'completed', 'completed_at' => now()->subHours(25),
        ]);

        Artisan::call('sitecare:notify-overdue-backups');
        Artisan::call('sitecare:notify-overdue-backups');
        Notification::assertSentToTimes($client, SiteCareAlert::class, 1);
        Notification::assertSentToTimes($technician, SiteCareAlert::class, 1);
        Notification::assertSentToTimes($admin, SiteCareAlert::class, 1);
        $this->assertNotNull($website->fresh()->backup_overdue_notified_at);

        $this->actingAs($technician)->postJson("/api/v1/websites/{$website->id}/backups", [
            'type' => 'full_site', 'status' => 'completed', 'completed_at' => now()->toIso8601String(),
        ])->assertCreated();
        $this->assertNull($website->fresh()->backup_overdue_notified_at);
        Artisan::call('sitecare:notify-overdue-backups');
        Notification::assertSentToTimes($client, SiteCareAlert::class, 1);
        Notification::assertSentToTimes($technician, SiteCareAlert::class, 1);
        Notification::assertSentToTimes($admin, SiteCareAlert::class, 1);
    }
}
