<?php

namespace Tests\Feature;

use App\Models\{Organisation,User,Website};
use App\Services\SafeWebsiteUrl;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class WebsiteWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_client_submission_records_care_preferences_without_starting_monitoring(): void
    {
        $organisation=Organisation::create(['name'=>'Northstar','slug'=>'northstar','contact_email'=>'ops@example.test']);
        $client=User::create(['name'=>'Client','email'=>'client@example.test','password'=>'password','role'=>'client','organisation_id'=>$organisation->id]);
        $this->mock(SafeWebsiteUrl::class,fn($mock)=>$mock->shouldReceive('assertPublic')->twice()->andReturn(['host'=>'example.test','port'=>443,'ip'=>'93.184.216.34','is_ip'=>false]));

        $response=$this->actingAs($client)->postJson('/api/v1/websites',[
            'name'=>'Northstar','url'=>'https://example.test','staging_url'=>'https://staging.example.test',
            'primary_contact'=>'Jamie Parker','contact_email'=>'jamie@example.test','backup_frequency_hours'=>168,
            'preferred_maintenance_window'=>'Weekday evenings','description'=>'Marketing site','technology_notes'=>'Laravel CMS','hosting_provider'=>'Example Host',
        ])->assertCreated()->assertJsonPath('data.status','pending');

        $website=Website::findOrFail($response->json('data.id'));
        $this->assertSame(15,$website->monitor_interval);
        $this->assertSame(168,$website->backup_frequency_hours);
        $this->assertDatabaseHas('websites',['id'=>$website->id,'status'=>'pending','last_checked_at'=>null]);
    }

    public function test_administrator_review_assigns_a_technician_and_configures_monitoring(): void
    {
        Notification::fake();
        $organisation=Organisation::create(['name'=>'Northstar','slug'=>'northstar','contact_email'=>'ops@example.test']);
        $client=User::create(['name'=>'Client','email'=>'client@example.test','password'=>'password','role'=>'client','organisation_id'=>$organisation->id]);
        $admin=User::create(['name'=>'Admin','email'=>'admin@example.test','password'=>'password','role'=>'admin']);
        $technician=User::create(['name'=>'Tech','email'=>'tech@example.test','password'=>'password','role'=>'technician']);
        $website=Website::create(['organisation_id'=>$organisation->id,'name'=>'Northstar','url'=>'https://example.test','status'=>'pending']);

        $this->actingAs($admin)->patchJson("/api/v1/websites/{$website->id}",[
            'status'=>'active','technician_id'=>$technician->id,'monitor_interval'=>60,'internal_verification_notes'=>'DNS and ownership checked.',
        ])->assertOk()->assertJsonPath('data.technician.id',$technician->id);
        $this->assertDatabaseHas('websites',['id'=>$website->id,'status'=>'active','technician_id'=>$technician->id,'monitor_interval'=>60]);
        $this->actingAs($client)->getJson('/api/v1/websites')->assertOk()->assertDontSee('DNS and ownership checked.');
    }
}
