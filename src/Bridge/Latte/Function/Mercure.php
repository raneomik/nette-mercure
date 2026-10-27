<?php

declare(strict_types=1);

namespace Raneomik\NetteMercure\Bridge\Latte\Function;

use Raneomik\NetteMercure\Bridge\DI\Config\ConfigurationRegistry;
use Raneomik\NetteMercure\Bridge\Utils\BroadcastersLoader;
use Raneomik\NetteMercure\Bridge\Utils\SubscriptionQuery;
use Raneomik\NetteMercure\Core\Publish\Broadcasters;
use Raneomik\NetteMercure\Core\Subscribe\JWTProviderInterface;
use Symfony\Component\Mercure\ProtocolVersion;

/**
 * @phpstan-type MercureOptions array{
 *       subscribe?: string|string[],
 *       publish?: string|string[],
 *       additionalClaims?: array<string, mixed>,
 *       lastEventId?: string,
 *       hub?: string,
 *       addJwt?: bool
 *   }
 * /
 */
final readonly class Mercure
{
    private Broadcasters $broadcasters;

    private function __construct(
        private JWTProviderInterface $jwtProvider,
        private BroadcastersLoader $broadcastersLoader,
        private ConfigurationRegistry $configuredData,
    ) {
    }

    /**
     * @param null|array<string,string[]>|string|string[] $topics A topic or an array of topics to subscribe for. If this parameter is omitted or `null` is passed, the URL of the hub will be returned (useful for publishing in JavaScript).
     * @param MercureOptions $options The options to pass to the JWT factory
     *
     * @return string The URL of the hub with the appropriate "topic" query parameters (if any)
     */
    public function __invoke(
        array|string|null $topics = null,
        ?string $hub = null,
        array $options = []
    ): string {
        $url = $this->broadcasters()
            ->broadcasterUrl($hub ??= $options['hub'] ?? null)
        ;

        return $url . $this->buildQuery(
            \is_string($topics) ? [$topics] : ($topics ?? []),
            $options,
            $hub,
        );
    }

    public static function build(
        JWTProviderInterface $jwtProvider,
        BroadcastersLoader $broadcastersLoader,
        ConfigurationRegistry $configuredData,
    ): self {
        return new self($jwtProvider, $broadcastersLoader, $configuredData);
    }

    private function broadcasters(): Broadcasters
    {
        return $this->broadcasters ??= ($this->broadcastersLoader)();
    }

    /**
     * @param array<string,string[]>|string[] $topics
     * @param MercureOptions $options
     */
    private function buildQuery(array $topics, array $options, ?string $hub): string
    {
        $params = SubscriptionQuery::params(
            $topics,
            $protocolVersion = $this->broadcasters()->broadcasterHub($hub)->getProtocolVersion(),
        );

        if ('' !== ($options['lastEventId'] ?? '')) {
            $params[] = 'lastEventID=' . rawurlencode($options['lastEventId']);
        }

        if (ProtocolVersion::V1 === $protocolVersion) {
            return SubscriptionQuery::build($params);
        }

        $hubData = $this->configuredData->getConfiguration($hub);

        if ($hubData->jwtInQueryParam || true === ($options['addJwt'] ?? false)) {
            $params[] = 'authorization=' . $this->jwtProvider->provide($hub, $options['subscribe'] ?? $hubData->subscribe, $options['additionalClaims'] ?? [])->token;
        }

        return SubscriptionQuery::build($params);
    }
}
