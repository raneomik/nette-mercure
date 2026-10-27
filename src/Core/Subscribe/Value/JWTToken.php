<?php

declare(strict_types=1);

namespace Raneomik\NetteMercure\Core\Subscribe\Value;

final readonly class JWTToken
{
    /**
     * @param array<string, mixed> $extraClaims
     */
    public function __construct(
        public string $token,
        public ?\DateTimeInterface $ttl,
        public array $extraClaims = [],
    ) {
    }
}
