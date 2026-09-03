<?php

declare(strict_types=1);

namespace Raneomik\NetteMercure;

use Raneomik\NetteMercure\Core\Subscribe\Value\SubscriptionData;

interface SubscriberInterface
{
    /**
     * @param string|string[] $topics
     * @param array<string, mixed> $extraClaims
     */
    public function subscribe(?string $hubName, array|string $topics, array $extraClaims = []): SubscriptionData;
}
