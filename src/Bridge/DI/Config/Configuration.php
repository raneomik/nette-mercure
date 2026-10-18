<?php

declare(strict_types=1);

namespace Raneomik\NetteMercure\Bridge\DI\Config;

use Symfony\Component\Mercure\ProtocolVersion;

final readonly class Configuration
{
    /**
     * @param string[] $subscribe
     * @param string[] $publish
     */
    public function __construct(
        public string $hubName,
        public string $hubUrl,
        public string $publicUrl,
        public array $subscribe,
        public array $publish,
        public bool $jwtInQueryParam = false,
        public ProtocolVersion $protocolVersion = ProtocolVersion::V1,
        public Claims $claims = new Claims(),
        public bool $useCookie = false,
        public bool $autoDiscovery = false,
    ) {
    }
}
