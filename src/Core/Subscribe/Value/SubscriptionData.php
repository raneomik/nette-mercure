<?php

declare(strict_types=1);

namespace Raneomik\NetteMercure\Core\Subscribe\Value;

final readonly class SubscriptionData
{
    /**
     * @param array<string, mixed> $jwtExtraClaims
     * @param string $subscribeUrl hub URL with the "match"/"topic" query, ready for EventSource
     */
    public function __construct(
        public string $jwtToken,
        public string $hubUrl,
        public string $subscribeUrl,
        public ?\DateTimeInterface $expiresAt,
        public array $jwtExtraClaims = [],
    ) {
    }
}
