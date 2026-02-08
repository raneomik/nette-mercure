<?php

declare(strict_types=1);

namespace Raneomik\NetteMercure\Core\Subscribe\Value;

final readonly class SubscriptionData
{
    /**
     * @param array<string, mixed> $jwtExtraClaims
     */
    public function __construct(
        public string $jwtToken,
        public string $hubUrl,
        public \DateTimeInterface $expiresAt,
        public array $jwtExtraClaims = [],
    ) {
    }
}
