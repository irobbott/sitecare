<?php

namespace App\Services;

use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SafeWebsiteUrl
{
    public function assertPublic(string $url): array
    {
        $parts=parse_url($url);
        if (!$parts || !in_array(strtolower($parts['scheme']??''),['http','https'],true) || isset($parts['user']) || isset($parts['pass']) || isset($parts['fragment'])) $this->reject();
        if (isset($parts['port']) && !in_array((int)$parts['port'],[80,443],true)) $this->reject();
        $host=rtrim(strtolower(trim($parts['host']??'','[]')),'.');
        if (!$host || $host==='localhost' || Str::endsWith($host,['.localhost','.local','.internal','.test','.lan'])) $this->reject();
        $addresses=filter_var($host,FILTER_VALIDATE_IP)?[$host]:[];
        if(!$addresses)foreach(dns_get_record($host,DNS_A|DNS_AAAA)?:[] as $record){if(isset($record['ip']))$addresses[]=$record['ip'];if(isset($record['ipv6']))$addresses[]=$record['ipv6'];}
        if (!$addresses) { $fallback=gethostbyname($host); if($fallback!==$host)$addresses[]=$fallback; }
        if (!$addresses) $this->reject();
        foreach ($addresses as $ip) if (!$this->publicIp($ip)) $this->reject();
        $port=(int)($parts['port']??(strtolower($parts['scheme'])==='https'?443:80));
        return ['host'=>$host,'port'=>$port,'ip'=>$addresses[0],'is_ip'=>(bool)filter_var($host,FILTER_VALIDATE_IP)];
    }
    private function publicIp(string $ip): bool
    {
        if(str_starts_with(strtolower($ip),'::ffff:'))return false;
        return (bool)filter_var($ip,FILTER_VALIDATE_IP,FILTER_FLAG_NO_PRIV_RANGE|FILTER_FLAG_NO_RES_RANGE);
    }
    private function reject(): never { throw ValidationException::withMessages(['url'=>'This address cannot be monitored. Use a public website on port 80 or 443.']); }
}
