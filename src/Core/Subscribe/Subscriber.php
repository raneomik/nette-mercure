<?php

declare(strict_types=1);

namespace Raneomik\NetteMercure\Core\Subscribe;

use Raneomik\NetteMercure\Bridge\DI\Config\ConfigurationRegistry;
use Raneomik\NetteMercure\Bridge\Utils\SubscriptionQuery;
use Raneomik\NetteMercure\Core\Localization\TopicLocalizer;
use Raneomik\NetteMercure\Core\Subscribe\Value\SubscriptionData;
use Raneomik\NetteMercure\SubscriberInterface;
use Symfony\Component\Mercure\Jwt\Grant;

final readonly class Subscriber implements SubscriberInterface
{
    public function __construct(
        private JWTProviderInterface $jwtProvider,
        private ConfigurationRegistry $config,
        private ?TopicLocalizer $topicLocalizer = null,
    ) {
    }

    #[\Override]
    public function subscribe(
        array|string $topics,
        ?string $hubName = null,
        array $extraClaims = [],
        ?string $locale = null,
    ): SubscriptionData {
        $hubData = $this->config->getConfiguration($hubName);
        $topics = (array) $topics;

        if ($this->topicLocalizer?->isLocalized($hubName)) {
            // a subscribe endpoint rarely carries the page locale itself: $locale (often <html lang>) wins when known
            $topics = TopicLocalizer::suffix($topics, $this->topicLocalizer->resolve($locale));
        }

        // built first: it rejects malformed topics before any token is issued
        $subscribeUrl = $hubData->publicUrl . SubscriptionQuery::build(SubscriptionQuery::params($topics, $hubData->protocolVersion));

        // a single "subscribe" grant: $topics often comes from the request, it must not be able to add "publish"
        $jwt = $this->jwtProvider->provide($hubName, [new Grant([Grant::ACTION_SUBSCRIBE], $topics)], $extraClaims);

        return new SubscriptionData($jwt, $hubData->publicUrl, $subscribeUrl);
    }
}
