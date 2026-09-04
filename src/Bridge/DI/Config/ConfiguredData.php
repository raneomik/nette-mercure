<?php

declare(strict_types=1);

namespace Raneomik\NetteMercure\Bridge\DI\Config;

final readonly class ConfiguredData
{
    /**
     * @param string[] $subscribe
     * @param string[] $publish
     */
    public function __construct(
        public string $hubName,
        public string $hubUrl,
        public array $subscribe,
        public array $publish,
        public bool $jwtInQueryParam = false,
        public bool $useCookie = false,
        public bool $autoDiscovery = false,
    ) {
    }
}
