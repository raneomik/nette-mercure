<?php

declare(strict_types=1);

namespace Raneomik\NetteMercure\Core\Subscribe;

use Raneomik\NetteMercure\Bridge\Utils\ConfiguredDataRegistry;
use Raneomik\NetteMercure\Core\Discovery;
use Raneomik\NetteMercure\Core\Subscribe\Value\SubscriptionData;
use Raneomik\NetteMercure\SubscriberInterface;

final readonly class Subscriber implements SubscriberInterface
{
    public function __construct(
        private AuthorizationInterface $authorization,
        private JWTProviderInterface $jwtProvider,
        private Discovery $discovery,
        private ConfiguredDataRegistry $config,
    ) {
    }

    #[\Override]
    public function subscribe(
        ?string $hubName = null,
        array|string|null $topics = ['*'],
        array $extraClaims = [],
    ): SubscriptionData {
        $hubData = $this->config->getConfiguration($hubName);

        $this->discovery->addLink($hubData->hubUrl);

        if (! $hubData->disableCookie) {
            $this->authorization->createCookie(
                $topics,
                $topics,
                $extraClaims,
                $hubName,
            );
        }

        return new SubscriptionData(
            $this->jwtProvider->provide($hubName, $topics, $topics, $extraClaims),
            $hubData->hubUrl,
            $this->jwtProvider->ttl(),
            $extraClaims,
        );
    }
}
