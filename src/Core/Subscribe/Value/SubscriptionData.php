<?php

declare(strict_types=1);

namespace Raneomik\NetteMercure\Core\Subscribe\Value;

final readonly class SubscriptionData
{
    /**
     * @param string $subscribeUrl hub URL with the "match"/"topic" query, ready for EventSource
     */
    public function __construct(
        public JWTToken $jwt,
        public string $hubUrl,
        public string $subscribeUrl,
    ) {
    }
}
