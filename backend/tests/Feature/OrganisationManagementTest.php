<?php

namespace Tests\Feature;

use App\Models\{Organisation,User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrganisationManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_administrators_can_create_and_suspend_organisations(): void
    {
        $admin=User::create(['name'=>'Admin','email'=>'admin@example.test','password'=>'password','role'=>'admin']);
        $client=User::create(['name'=>'Client','email'=>'client@example.test','password'=>'password','role'=>'client']);

        $this->actingAs($client)->getJson('/api/v1/organisations')->assertForbidden();
        $created=$this->actingAs($admin)->postJson('/api/v1/organisations',[
            'name'=>'Northstar Studio','contact_email'=>'ops@northstar.example','primary_contact'=>'Jamie Parker',
        ])->assertCreated()->assertJsonPath('data.slug','northstar-studio');
        $id=$created->json('data.id');
        $this->actingAs($admin)->patchJson("/api/v1/organisations/{$id}",['status'=>'suspended'])->assertOk()->assertJsonPath('data.status','suspended');
        $this->assertDatabaseHas('organisations',['id'=>$id,'status'=>'suspended']);
    }

    public function test_client_roles_must_be_attached_to_an_active_organisation_and_admins_cannot_change_their_own_role(): void
    {
        $admin=User::create(['name'=>'Admin','email'=>'admin@example.test','password'=>'password','role'=>'admin']);
        $organisation=Organisation::create(['name'=>'Paused','slug'=>'paused','contact_email'=>'paused@example.test','status'=>'suspended']);
        $member=User::create(['name'=>'Tech','email'=>'tech@example.test','password'=>'password','role'=>'technician']);

        $this->actingAs($admin)->patchJson("/api/v1/team/{$member->id}",['role'=>'client','organisation_id'=>$organisation->id])->assertUnprocessable();
        $this->actingAs($admin)->patchJson("/api/v1/team/{$admin->id}",['role'=>'technician'])->assertUnprocessable();
    }
}
