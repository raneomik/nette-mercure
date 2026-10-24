<?php

declare(strict_types=1);

namespace Raneomik\NetteMercure;

use Raneomik\NetteMercure\Core\Subscribe\Value\SubscriptionData;

interface SubscriberInterface
{
    /**
     * @param array<string, string[]>|string|string[] $topics a topic, topics, or matcher type => patterns (protocol 1.0)
     * @param array<string, mixed> $extraClaims
     */
    public function subscribe(?string $hubName, array|string $topics, array $extraClaims = []): SubscriptionData;
}
