<?php

declare(strict_types=1);

namespace Raneomik\NetteMercure\Core\Subscribe;

use Raneomik\NetteMercure\Bridge\DI\Config\ConfigurationRegistry;
use Raneomik\NetteMercure\Bridge\Utils\SubscriptionQuery;
use Raneomik\NetteMercure\Core\Subscribe\Value\SubscriptionData;
use Raneomik\NetteMercure\SubscriberInterface;
use Symfony\Component\Mercure\Jwt\Grant;

final readonly class Subscriber implements SubscriberInterface
{
    public function __construct(
        private JWTProviderInterface $jwtProvider,
        private ConfigurationRegistry $config,
    ) {
    }

    #[\Override]
    public function subscribe(
        ?string $hubName = null,
        array|string $topics = ['*'],
        array $extraClaims = [],
    ): SubscriptionData {
        $hubData = $this->config->getConfiguration($hubName);
        $topics = (array) $topics;
        // built first: it rejects malformed topics before any token is issued
        $subscribeUrl = $hubData->publicUrl . SubscriptionQuery::build(SubscriptionQuery::params($topics, $hubData->protocolVersion));

        // a single "subscribe" grant: $topics often comes from the request, it must not be able to add "publish"
        $jwt = $this->jwtProvider->provide($hubName, [new Grant([Grant::ACTION_SUBSCRIBE], $topics)], $extraClaims);

        return new SubscriptionData($jwt, $hubData->publicUrl, $subscribeUrl);
    }
}
