<?php

namespace App\Services;

use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SafeWebsiteUrl
{
    public function assertPublic(string $url): void
    {
        $parts=parse_url($url);
        if (!$parts || !in_array(strtolower($parts['scheme']??''),['http','https'],true) || isset($parts['user']) || isset($parts['pass']) || isset($parts['fragment'])) $this->reject();
        if (isset($parts['port']) && !in_array((int)$parts['port'],[80,443],true)) $this->reject();
        $host=rtrim(strtolower($parts['host']??''),'.');
        if (!$host || $host==='localhost' || Str::endsWith($host,['.localhost','.local','.internal','.test','.lan']) || filter_var($host,FILTER_VALIDATE_IP) && !$this->publicIp($host)) $this->reject();
        $addresses=filter_var($host,FILTER_VALIDATE_IP)?[$host]:(gethostbynamel($host)?:[]);
        if (!$addresses) $this->reject();
        foreach ($addresses as $ip) if (!$this->publicIp($ip)) $this->reject();
    }
    private function publicIp(string $ip): bool { return (bool) filter_var($ip,FILTER_VALIDATE_IP,FILTER_FLAG_NO_PRIV_RANGE|FILTER_FLAG_NO_RES_RANGE); }
    private function reject(): never { throw ValidationException::withMessages(['url'=>'This address cannot be monitored. Use a public website on port 80 or 443.']); }
}
