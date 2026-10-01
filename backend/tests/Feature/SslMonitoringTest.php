<?php

namespace Tests\Feature;

use App\Models\{Organisation,SslCheck,User,Website};
use App\Services\{SafeWebsiteUrl,SslCertificateMonitor};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SslMonitoringTest extends TestCase
{
    use RefreshDatabase;

    public function test_http_sites_are_reported_as_not_applicable_without_a_network_request(): void
    {
        [$website]=$this->account('plain','http://example.test');
        $check=app(SslCertificateMonitor::class)->check($website,app(SafeWebsiteUrl::class));

        $this->assertFalse($check->monitored);
        $this->assertNull($check->certificate_valid);
        $this->assertDatabaseCount('ssl_checks',1);
    }

    public function test_private_https_addresses_are_rejected_before_tls_connection(): void
    {
        [$website]=$this->account('private','https://127.0.0.1');
        $check=app(SslCertificateMonitor::class)->check($website,app(SafeWebsiteUrl::class));

        $this->assertFalse($check->certificate_valid);
        $this->assertSame('Certificate inspection could not complete.',$check->error_message);
        $this->assertDatabaseCount('ssl_checks',1);
    }

    public function test_website_health_history_is_scoped_to_the_client_organisation(): void
    {
        [$website,$client]=$this->account('owned','https://example.test');
        SslCheck::create(['website_id'=>$website->id,'checked_at'=>now(),'monitored'=>true,'certificate_valid'=>true,'hostname_matches'=>true,'subject'=>'example.test','issuer'=>'Example CA','expires_at'=>now()->addDays(20),'days_remaining'=>20]);
        $this->actingAs($client)->getJson("/api/v1/websites/{$website->id}/health")->assertOk()->assertJsonPath('data.ssl.subject','example.test');

        [$other]=$this->account('other','https://other.example.test');
        $this->actingAs($client)->getJson("/api/v1/websites/{$other->id}/health")->assertNotFound();
    }

    private function account(string $slug,string $url): array
    {
        $organisation=Organisation::create(['name'=>$slug,'slug'=>$slug,'contact_email'=>$slug.'@example.test']);
        $client=User::create(['name'=>'Client '.$slug,'email'=>$slug.'@example.test','password'=>'password','role'=>'client','organisation_id'=>$organisation->id]);
        $website=Website::create(['organisation_id'=>$organisation->id,'name'=>'Site '.$slug,'url'=>$url,'status'=>'active']);
        return [$website,$client];
    }
}
