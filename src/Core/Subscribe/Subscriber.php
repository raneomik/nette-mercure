<?php

declare(strict_types=1);

namespace Raneomik\NetteMercure\Core\Subscribe;

use Raneomik\NetteMercure\Bridge\Utils\ConfiguredDataRegistry;
use Raneomik\NetteMercure\Core\Subscribe\Value\SubscriptionData;
use Raneomik\NetteMercure\SubscriberInterface;

final readonly class Subscriber implements SubscriberInterface
{
    public function __construct(
        private JWTProviderInterface $jwtProvider,
        private ConfiguredDataRegistry $config,
    ) {
    }

    #[\Override]
    public function subscribe(
        ?string $hubName = null,
        array|string $topics = ['*'],
        array $extraClaims = [],
    ): SubscriptionData {
        $hubData = $this->config->getConfiguration($hubName);

        return new SubscriptionData(
            $this->jwtProvider->provide($hubName, $topics, $extraClaims),
            $hubData->hubUrl,
            $this->jwtProvider->ttl(),
            $extraClaims,
        );
    }
}
