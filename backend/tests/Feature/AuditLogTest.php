<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_administrators_can_page_through_the_full_audit_log_but_clients_cannot_read_it(): void
    {
        $admin = User::create(['name' => 'Admin', 'email' => 'admin@example.test', 'password' => 'password', 'role' => 'admin']);
        $client = User::create(['name' => 'Client', 'email' => 'client@example.test', 'password' => 'password', 'role' => 'client']);
        for ($index = 1; $index <= 51; $index++) {
            AuditLog::create(['actor_id' => $admin->id, 'action' => 'test.event_'.$index, 'subject_type' => 'TestRecord', 'subject_id' => $index]);
        }

        $this->actingAs($admin)->getJson('/api/v1/audit-logs?page=2')->assertOk()
            ->assertJsonPath('current_page', 2)
            ->assertJsonPath('last_page', 2)
            ->assertJsonCount(1, 'data');
        $this->actingAs($client)->getJson('/api/v1/audit-logs')->assertForbidden();
    }
}
