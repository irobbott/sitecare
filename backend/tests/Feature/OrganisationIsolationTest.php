<?php

namespace Tests\Feature;

use App\Models\{Organisation,Ticket,TicketComment,User,Website};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrganisationIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_clients_only_receive_their_own_tickets_and_public_comments():void
    {
        [$first,$websiteA,$clientA]=$this->account('first');
        [, $websiteB,$clientB]=$this->account('second');
        $own=Ticket::create(['number'=>'SC-2026-00001','organisation_id'=>$first->id,'website_id'=>$websiteA->id,'reporter_id'=>$clientA->id,'subject'=>'Own issue','description'=>'Own description','priority'=>'normal','status'=>'open']);
        $other=Ticket::create(['number'=>'SC-2026-00002','organisation_id'=>$websiteB->organisation_id,'website_id'=>$websiteB->id,'reporter_id'=>$clientB->id,'subject'=>'Other issue','description'=>'Private description','priority'=>'normal','status'=>'open']);
        TicketComment::create(['ticket_id'=>$own->id,'user_id'=>$clientA->id,'body'=>'Client reply','internal'=>false]);
        TicketComment::create(['ticket_id'=>$own->id,'user_id'=>$clientA->id,'body'=>'Internal staff note','internal'=>true]);

        $this->actingAs($clientA)->getJson('/api/v1/tickets')->assertOk()->assertSee('Own issue')->assertDontSee('Other issue');
        $this->actingAs($clientA)->getJson("/api/v1/tickets/{$own->id}/history")->assertOk();
        $this->actingAs($clientA)->getJson("/api/v1/tickets/{$own->id}/comments")->assertOk()->assertSee('Client reply')->assertDontSee('Internal staff note');
        $this->actingAs($clientA)->getJson("/api/v1/tickets/{$other->id}/comments")->assertNotFound();
        $this->actingAs($clientA)->getJson("/api/v1/tickets/{$other->id}/history")->assertNotFound();
    }

    public function test_client_cannot_triage_its_own_ticket():void
    {
        [$organisation,$website,$client]=$this->account('client');
        $ticket=Ticket::create(['number'=>'SC-2026-00003','organisation_id'=>$organisation->id,'website_id'=>$website->id,'reporter_id'=>$client->id,'subject'=>'Issue','description'=>'Details','priority'=>'normal','status'=>'open']);
        $this->actingAs($client)->patchJson("/api/v1/tickets/{$ticket->id}",['status'=>'triaged'])->assertUnprocessable();
    }

    private function account(string $slug):array
    {
        $organisation=Organisation::create(['name'=>$slug,'slug'=>$slug,'contact_email'=>$slug.'@example.test']);
        $client=User::create(['name'=>'Client '.$slug,'email'=>$slug.'@example.test','password'=>'password','role'=>'client','organisation_id'=>$organisation->id]);
        $website=Website::create(['organisation_id'=>$organisation->id,'name'=>'Site '.$slug,'url'=>'https://example.test','status'=>'active']);
        return [$organisation,$website,$client];
    }
}
