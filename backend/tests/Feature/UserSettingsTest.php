<?php

namespace Tests\Feature;

use App\Models\{Organisation,User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_users_can_update_their_own_profile_and_notification_preferences(): void
    {
        $organisation=Organisation::create(['name'=>'Northstar','slug'=>'northstar','contact_email'=>'ops@example.test']);
        $user=User::create(['name'=>'Jamie','email'=>'jamie@example.test','password'=>'current password','role'=>'client','organisation_id'=>$organisation->id]);
        $this->actingAs($user)->patchJson('/api/v1/auth/profile',['name'=>'Jamie Parker','email'=>'jamie.parker@example.test'])
            ->assertOk()->assertJsonPath('data.name','Jamie Parker')->assertJsonPath('data.email','jamie.parker@example.test');
        $this->actingAs($user)->patchJson('/api/v1/notification-preferences',['in_app'=>false,'email'=>true])
            ->assertOk()->assertJsonPath('data.in_app',false);
        $this->actingAs($user)->getJson('/api/v1/notification-preferences')->assertOk()->assertJsonPath('data.email',true);
    }

    public function test_changing_a_password_requires_the_current_password_and_invalidates_other_sessions(): void
    {
        $user=User::create(['name'=>'Jamie','email'=>'jamie@example.test','password'=>'current password','role'=>'admin']);
        \Illuminate\Support\Facades\DB::table('sessions')->insert(['id'=>'other-session','user_id'=>$user->id,'ip_address'=>'127.0.0.1','user_agent'=>'test','payload'=>'payload','last_activity'=>now()->timestamp]);
        $payload=['current_password'=>'wrong password','password'=>'a new stronger password','password_confirmation'=>'a new stronger password'];
        $this->actingAs($user)->patchJson('/api/v1/auth/profile',$payload)->assertUnprocessable();
        $payload['current_password']='current password';
        $this->actingAs($user)->patchJson('/api/v1/auth/profile',$payload)->assertOk();

        $this->assertTrue(Hash::check($payload['password'],$user->fresh()->password));
        $this->assertDatabaseMissing('sessions',['id'=>'other-session']);
    }
}
