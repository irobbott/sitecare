<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\QueuedPasswordReset;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class PasswordRecoveryTest extends TestCase
{
    use RefreshDatabase;

    public function test_password_reset_request_is_generic_and_queues_email_for_existing_accounts(): void
    {
        Notification::fake();
        $user=User::create(['name'=>'Client','email'=>'client@example.test','password'=>'old password','role'=>'client']);

        $this->postJson('/api/v1/auth/forgot-password',['email'=>'missing@example.test'])->assertAccepted()->assertJsonPath('message','If an account matches that email, a reset link will be sent.');
        $this->postJson('/api/v1/auth/forgot-password',['email'=>$user->email])->assertAccepted()->assertJsonPath('message','If an account matches that email, a reset link will be sent.');

        Notification::assertSentTo($user,QueuedPasswordReset::class);
    }

    public function test_password_reset_replaces_the_hash_and_rejects_reuse_of_the_token(): void
    {
        $user=User::create(['name'=>'Client','email'=>'client@example.test','password'=>'old password','role'=>'client']);
        $token=Password::createToken($user);
        $payload=['email'=>$user->email,'token'=>$token,'password'=>'a stronger password 2026','password_confirmation'=>'a stronger password 2026'];

        $this->postJson('/api/v1/auth/reset-password',$payload)->assertOk();
        $this->assertTrue(Hash::check($payload['password'],$user->fresh()->password));
        $this->postJson('/api/v1/auth/reset-password',$payload)->assertUnprocessable();
    }
}
