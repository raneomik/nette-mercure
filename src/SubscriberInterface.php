<?php

declare(strict_types=1);

namespace Raneomik\NetteMercure;

use Raneomik\NetteMercure\Core\Subscribe\Value\SubscriptionData;

interface SubscriberInterface
{
    /**
     * @param array<string, string[]>|string|string[] $topics a topic, topics, or matcher type => patterns (protocol 1.0)
     * @param array<string, mixed> $extraClaims
     * @param null|string $locale on a localized hub, the locale to subscribe in (ignored when unknown); the current one by default
     */
    public function subscribe(array|string $topics, ?string $hubName = null, array $extraClaims = [], ?string $locale = null): SubscriptionData;
}
