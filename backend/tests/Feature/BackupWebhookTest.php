<?php

namespace Tests\Feature;

use App\Models\Organisation;
use App\Models\Website;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BackupWebhookTest extends TestCase
{
    use RefreshDatabase;

    public function test_signed_backup_events_are_accepted_once():void
    {
        $organisation=Organisation::create(['name'=>'Example','slug'=>'example','contact_email'=>'ops@example.test']);
        $website=Website::create(['organisation_id'=>$organisation->id,'name'=>'Example site','url'=>'https://example.com','status'=>'active','webhook_secret'=>'correct horse battery staple']);
        $timestamp=(string)now()->timestamp;$event='backup-2026-001';
        $body=json_encode(['type'=>'full_site','status'=>'completed','completed_at'=>now()->toIso8601String(),'verified'=>false],JSON_THROW_ON_ERROR);
        $signature=hash_hmac('sha256',$timestamp."\n".$event."\n".$body,'correct horse battery staple');
        $headers=['X-SiteCare-Timestamp'=>$timestamp,'X-SiteCare-Event-Id'=>$event,'X-SiteCare-Signature'=>$signature,'Accept'=>'application/json','Content-Type'=>'application/json'];
        $path="/api/v1/webhooks/websites/{$website->id}/backups";

        $this->call('POST',$path,[],[],[],$headers,$body)->assertCreated();
        $this->call('POST',$path,[],[],[],$headers,$body)->assertConflict();
        $this->assertDatabaseCount('backup_records',1);
        $this->assertDatabaseCount('backup_webhook_events',1);
    }
}
