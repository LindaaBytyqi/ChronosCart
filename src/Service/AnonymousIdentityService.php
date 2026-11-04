<?php

namespace App\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;

class AnonymousIdentityService
{
    public function __construct(
        #[Autowire('%kernel.secret%')]
        private readonly string $appSecret,
    ) {
    }

    public function getIdentityData(Request $request): array
    {
        
        $visitorToken = $request->cookies->get('chronoscart_visitor');

        
        if (
            !is_string($visitorToken)
            || !preg_match('/^[a-f0-9]{64}$/', $visitorToken)
        ) {
            $visitorToken = bin2hex(random_bytes(32));
        }

        $identityHash = hash_hmac(
            'sha256',
            'visitor:' . $visitorToken,
            $this->appSecret
        );

        $clientIp = $request->getClientIp();

        $ipHash = $clientIp !== null
            ? hash_hmac('sha256', 'ip:' . $clientIp, $this->appSecret)
            : null;

        return [
            'token' => $visitorToken,
            'identityHash' => $identityHash,
            'ipHash' => $ipHash,
        ];
    }
}