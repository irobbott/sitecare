<?php

namespace Tests\Feature;

use App\Models\{Organisation,Ticket,User,Website};
use App\Notifications\SiteCareAlert;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class TicketNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_replies_notify_assigned_staff_but_internal_notes_do_not(): void
    {
        Notification::fake();
        $organisation=Organisation::create(['name'=>'Northstar','slug'=>'northstar','contact_email'=>'client@example.test']);
        $client=User::create(['name'=>'Client','email'=>'client@example.test','password'=>'password','role'=>'client','organisation_id'=>$organisation->id]);
        $technician=User::create(['name'=>'Tech','email'=>'tech@example.test','password'=>'password','role'=>'technician']);
        $website=Website::create(['organisation_id'=>$organisation->id,'technician_id'=>$technician->id,'name'=>'Northstar site','url'=>'https://example.test','status'=>'active']);
        $ticket=Ticket::create(['number'=>'SC-2026-00009','organisation_id'=>$organisation->id,'website_id'=>$website->id,'reporter_id'=>$client->id,'assignee_id'=>$technician->id,'subject'=>'Form issue','description'=>'Details','priority'=>'normal','status'=>'in_progress']);

        $this->actingAs($client)->postJson("/api/v1/tickets/{$ticket->id}/comments",['body'=>'The issue still happens.','internal'=>true])->assertCreated();
        Notification::assertNothingSent();

        $this->actingAs($client)->postJson("/api/v1/tickets/{$ticket->id}/comments",['body'=>'I can reproduce it now.'])->assertCreated();
        Notification::assertSentTo($technician,SiteCareAlert::class);
    }
}
