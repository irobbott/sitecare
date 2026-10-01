<?php

namespace Tests\Unit;

use App\Services\SafeWebsiteUrl;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SafeWebsiteUrlTest extends TestCase
{
    public function test_a_public_ip_on_an_allowed_port_is_accepted():void
    {
        app(SafeWebsiteUrl::class)->assertPublic('https://1.1.1.1/health');
        $this->assertTrue(true);
    }

    #[DataProvider('unsafeTargets')]
    public function test_private_reserved_or_unsupported_targets_are_rejected(string $url):void
    {
        $this->expectException(ValidationException::class);
        app(SafeWebsiteUrl::class)->assertPublic($url);
    }

    public static function unsafeTargets():array
    {
        return [
            'loopback'=>['http://127.0.0.1/'],
            'private network'=>['http://192.168.1.20/'],
            'link local'=>['http://169.254.169.254/'],
            'unique local ipv6'=>['http://[fd00::1]/'],
            'mapped loopback ipv6'=>['http://[::ffff:127.0.0.1]/'],
            'credentials'=>['https://user:secret@example.com/'],
            'unsupported port'=>['http://1.1.1.1:8080/'],
            'unsupported scheme'=>['file:///etc/passwd'],
        ];
    }
}
