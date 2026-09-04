<?php

declare(strict_types=1);

namespace Raneomik\NetteMercure\Bridge\Latte\Function;

use Raneomik\NetteMercure\Bridge\Utils\BroadcastersLoader;
use Raneomik\NetteMercure\Bridge\Utils\ConfiguredDataRegistry;
use Raneomik\NetteMercure\Bridge\Utils\GrantTopicNormalizer;
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
        private ConfiguredDataRegistry $configuredData,
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
            ->broadcasterUrl($options['hub'] ?? $hub)
        ;

        return $url.$this->buildQuery(
            \is_string($topics) ? [$topics] : ($topics ?? []),
            $options,
            $hub,
        );
    }

    public static function build(
        JWTProviderInterface $jwtProvider,
        BroadcastersLoader $broadcastersLoader,
        ConfiguredDataRegistry $configuredData,
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
        $hubInstance = $this->broadcasters()
            ->broadcasterHub($hub)
        ;

        $query = '';
        $separator = '?';
        if (ProtocolVersion::V1 === $hubInstance->getProtocolVersion()) {
            $normalized = GrantTopicNormalizer::normalize($topics);
            foreach ($normalized as $matcherType => $patterns) {
                $paramName = 'exact' === $matcherType ? 'match' : 'match_'.rawurlencode($matcherType);
                $patterns = \is_array($patterns) ? $patterns : [$patterns];

                foreach ($patterns as $pattern) {
                    $query .= $separator.$paramName.'='.rawurlencode($pattern);
                    $separator = '&';
                }
            }
        } else {
            foreach (GrantTopicNormalizer::flattenToExactOrFail($topics) as $topic) {
                $query .= $separator.'topic='.rawurlencode($topic);
                $separator = '&';
            }
        }

        if ('' !== ($options['lastEventId'] ?? '')) {
            $encodedLastEventId = rawurlencode($options['lastEventId']);
            $query .= $separator.'lastEventID='.$encodedLastEventId;
            $separator = '&';
        }

        $hubData = $this->configuredData->getConfiguration($hub);

        if ($hubData->jwtInQueryParam || true === ($options['addJwt'] ?? false)) {
            $query .= $separator.'authorization='.$this->jwtProvider->provide(
                $hub,
                $options['subscribe'] ?? $hubData->subscribe,
                $options['additionalClaims'] ?? [],
            );
        }

        return $query;
    }
}
