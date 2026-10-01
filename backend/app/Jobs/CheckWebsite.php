<?php

namespace App\Jobs;

use App\Models\{Incident,UptimeCheck,Website};
use App\Services\SafeWebsiteUrl;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;

class CheckWebsite implements ShouldQueue,ShouldBeUnique
{
    use Dispatchable,InteractsWithQueue,Queueable,SerializesModels;
    public int $uniqueFor=900;
    public function __construct(public int $websiteId){}
    public function uniqueId():string{return (string)$this->websiteId;}
    public function handle(SafeWebsiteUrl $safe):void
    {
        $website=Website::with('organisation')->find($this->websiteId);
        if(!$website||$website->status!=='active'||$website->organisation?->status!=='active')return;
        $checked=now();$start=microtime(true);$code=null;$error=null;$message=null;
        try{$target=$safe->assertPublic($website->url);if(!extension_loaded('curl'))throw new \RuntimeException('The cURL extension is required for pinned monitoring requests.');$curl=[CURLOPT_MAXFILESIZE=>1048576];if(!$target['is_ip']){$address=str_contains($target['ip'],':')?'['.$target['ip'].']':$target['ip'];$curl[CURLOPT_RESOLVE]=[$target['host'].':'.$target['port'].':'.$address];}$response=Http::timeout(12)->connectTimeout(4)->withOptions(['allow_redirects'=>false,'verify'=>true,'curl'=>$curl])->withUserAgent('SiteCare-Monitor/1.0 (+https://sitecare.local)')->get($website->url);$code=$response->status();$available=$code>=200&&$code<400;if(!$available){$error='http';$message='The website returned HTTP '.$code;}}
        catch(\Throwable $exception){$available=false;$error=$exception instanceof ConnectionException?'connection':'check';$message='Website check could not complete.';}
        $elapsed=(int)round((microtime(true)-$start)*1000);
        UptimeCheck::create(['website_id'=>$website->id,'checked_url'=>$website->url,'checked_at'=>$checked,'status_code'=>$code,'available'=>$available,'response_ms'=>$elapsed,'error_type'=>$error,'message'=>$message]);
        $website->update(['last_checked_at'=>$checked,'is_up'=>$available,'response_ms'=>$elapsed]);
        $incident=Incident::where('website_id',$website->id)->whereNull('recovered_at')->first();
        if(!$available){$recent=UptimeCheck::where('website_id',$website->id)->latest('checked_at')->take(2)->pluck('available');$consecutive=$recent->count()===2&&$recent->every(fn($state)=>!(bool)$state);if($incident){$incident->increment('failed_checks');$incident->update(['last_error'=>$message]);}elseif($consecutive){Incident::create(['website_id'=>$website->id,'started_at'=>$checked,'status'=>'open','last_error'=>$message,'failed_checks'=>2]);}}
        elseif($incident){$incident->update(['recovered_at'=>$checked,'status'=>'recovered']);}
    }
}
