<?php

namespace Tests\Feature;

use App\Models\{Organisation,Ticket,User,Website};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TicketAttachmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_clients_can_upload_and_download_files_on_their_own_tickets(): void
    {
        Storage::fake('local');
        [$organisation,$website,$client]=$this->account('client');
        $ticket=$this->ticket($organisation,$website,$client,'SC-2026-00001');

        $upload=$this->actingAs($client)->postJson("/api/v1/tickets/{$ticket->id}/attachments",[
            'file'=>UploadedFile::fake()->createWithContent('debug.txt','A short diagnostic log.'),
        ]);

        $upload->assertCreated()->assertJsonPath('data.original_name','debug.txt');
        $id=$upload->json('data.id');
        $attachment=\App\Models\TicketAttachment::findOrFail($id);
        Storage::disk('local')->assertExists($attachment->path);
        $this->actingAs($client)->get("/api/v1/attachments/{$id}")->assertOk()->assertHeader('X-Content-Type-Options','nosniff');
    }

    public function test_attachments_are_private_and_executable_files_are_rejected(): void
    {
        Storage::fake('local');
        [$organisation,$website,$client]=$this->account('first');
        $ticket=$this->ticket($organisation,$website,$client,'SC-2026-00002');
        $upload=$this->actingAs($client)->postJson("/api/v1/tickets/{$ticket->id}/attachments",[
            'file'=>UploadedFile::fake()->createWithContent('payload.php','<?php echo 1;'),
        ]);
        $upload->assertUnprocessable();

        [,,$otherClient]=$this->account('second');
        $attachment=$ticket->attachments()->create([
            'uploaded_by'=>$client->id,'disk'=>'local','path'=>'tickets/private.txt','original_name'=>'private.txt',
            'mime_type'=>'text/plain','size_bytes'=>1,
        ]);
        Storage::disk('local')->put('tickets/private.txt','x');
        $this->actingAs($otherClient)->get("/api/v1/attachments/{$attachment->id}")->assertNotFound();
    }

    private function account(string $slug): array
    {
        $organisation=Organisation::create(['name'=>$slug,'slug'=>$slug,'contact_email'=>$slug.'@example.test']);
        $client=User::create(['name'=>'Client '.$slug,'email'=>$slug.'@example.test','password'=>'password','role'=>'client','organisation_id'=>$organisation->id]);
        $website=Website::create(['organisation_id'=>$organisation->id,'name'=>'Site '.$slug,'url'=>'https://example.test','status'=>'active']);
        return [$organisation,$website,$client];
    }

    private function ticket(Organisation $organisation,Website $website,User $client,string $number): Ticket
    {
        return Ticket::create(['number'=>$number,'organisation_id'=>$organisation->id,'website_id'=>$website->id,'reporter_id'=>$client->id,'subject'=>'Support request','description'=>'Details','priority'=>'normal','status'=>'open']);
    }
}
