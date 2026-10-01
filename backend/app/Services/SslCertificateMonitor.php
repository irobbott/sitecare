<?php

namespace App\Services;

use App\Models\{SslCheck,Website};
use App\Notifications\SiteCareAlert;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SslCertificateMonitor
{
    public function check(Website $website, SafeWebsiteUrl $safe): ?SslCheck
    {
        if (strtolower((string) parse_url($website->url, PHP_URL_SCHEME)) !== 'https') {
            return SslCheck::create(['website_id'=>$website->id,'checked_at'=>now(),'monitored'=>false]);
        }

        try {
            $target=$safe->assertPublic($website->url);
            if (!function_exists('stream_socket_client') || !function_exists('openssl_x509_parse')) {
                return $this->recordFailure($website,'OpenSSL stream support is unavailable.');
            }
            $certificate=$this->captureCertificate($target,false);
            if (!$certificate) return $this->recordFailure($website,'The TLS certificate could not be read.');
            $parsed=openssl_x509_parse($certificate);
            if (!$parsed) return $this->recordFailure($website,'The TLS certificate could not be parsed.');

            $host=$target['host'];
            $validFrom=(int)($parsed['validFrom_time_t']??0);
            $expires=(int)($parsed['validTo_time_t']??0);
            $days=$expires ? (int) floor(($expires-now()->timestamp)/86400) : null;
            $hostnameMatches=(bool)openssl_x509_checkhost($certificate,$host);
            $chainValid=$this->captureCertificate($target,true)!==null;
            $valid=$chainValid&&$hostnameMatches&&$validFrom<=now()->timestamp&&$expires>now()->timestamp;
            $subject=(string)($parsed['subject']['CN']??$host);
            $issuer=(string)($parsed['issuer']['CN']??$parsed['issuer']['O']??'Unknown issuer');

            $check=SslCheck::create([
                'website_id'=>$website->id,'checked_at'=>now(),'monitored'=>true,
                'subject'=>mb_substr($subject,0,255),'issuer'=>mb_substr($issuer,0,255),
                'valid_from'=>$validFrom?date('Y-m-d H:i:s',$validFrom):null,
                'expires_at'=>$expires?date('Y-m-d H:i:s',$expires):null,'days_remaining'=>$days,
                'certificate_valid'=>$chainValid&&$validFrom<=now()->timestamp&&$expires>now()->timestamp,
                'hostname_matches'=>$hostnameMatches,
                'error_message'=>$valid?null:(!$hostnameMatches?'Certificate hostname does not match the website.':(!$chainValid?'Certificate chain validation failed.':($days<0?'The certificate has expired.':'The certificate is not currently valid.'))),
            ]);
            $this->sendThresholdAlert($website,$check);
            return $check;
        } catch (\Throwable $exception) {
            Log::notice('SSL certificate check failed.', ['website_id'=>$website->id,'error_type'=>class_basename($exception)]);
            return $this->recordFailure($website,'Certificate inspection could not complete.');
        }
    }

    private function captureCertificate(array $target,bool $verify): mixed
    {
        $ip=$target['ip'];
        $address=str_contains($ip,':')?'['.$ip.']':$ip;
        $context=stream_context_create(['ssl'=>[
            'capture_peer_cert'=>true,'peer_name'=>$target['host'],'SNI_enabled'=>true,
            'verify_peer'=>$verify,'verify_peer_name'=>$verify,'allow_self_signed'=>!$verify,
        ]]);
        $errno=0;$error='';
        $socket=@stream_socket_client('tcp://'.$address.':'.$target['port'],$errno,$error,5,STREAM_CLIENT_CONNECT,$context);
        if (!$socket) return null;
        stream_set_timeout($socket,5);
        $secured=@stream_socket_enable_crypto($socket,true,STREAM_CRYPTO_METHOD_TLS_CLIENT);
        $options=stream_context_get_options($socket);
        $certificate=$options['ssl']['peer_certificate']??null;
        fclose($socket);
        return $secured&&$certificate?$certificate:null;
    }

    private function recordFailure(Website $website,string $message): SslCheck
    {
        return SslCheck::create(['website_id'=>$website->id,'checked_at'=>now(),'monitored'=>true,'certificate_valid'=>false,'error_message'=>$message]);
    }

    private function sendThresholdAlert(Website $website,SslCheck $check): void
    {
        if (!$check->expires_at || $check->days_remaining===null) return;
        $previousDays=$website->sslChecks()->where('id','!=',$check->id)->whereNotNull('days_remaining')->latest('checked_at')->value('days_remaining');
        $thresholds=[30,14,7,3,1,0];
        $nearestThreshold=collect($thresholds)->filter(fn($threshold)=>$check->days_remaining<=$threshold)->min();
        foreach ($thresholds as $threshold) {
            if ($check->days_remaining>$threshold) continue;
            if ($previousDays===null&&$threshold!==$nearestThreshold) continue;
            if ($previousDays!==null&&$previousDays<=$threshold) continue;
            $inserted=DB::table('ssl_alerts')->insertOrIgnore([
                'website_id'=>$website->id,'certificate_expires_on'=>$check->expires_at->toDateString(),
                'threshold_days'=>$threshold,'sent_at'=>now(),
            ]);
            if (!$inserted) continue;
            $users=\App\Models\User::where('organisation_id',$website->organisation_id)->where('role','client')->get();
            if ($website->technician) $users->push($website->technician);
            $users=$users->merge(\App\Models\User::where('role','admin')->get());
            foreach ($users->unique('id') as $user) {
                $user->notify(new SiteCareAlert('SSL certificate expiring',$website->name.' certificate expires in '.$check->days_remaining.' day(s).','/'));
            }
        }
    }
}
