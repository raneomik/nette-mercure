<?php

declare(strict_types=1);

namespace Raneomik\NetteMercure\Bridge\Latte;

use Latte\Extension;
use Raneomik\NetteMercure\Bridge\Utils\BroadcastersLoader;
use Raneomik\NetteMercure\Bridge\Utils\ConfiguredDataRegistry;
use Raneomik\NetteMercure\Bridge\Utils\MatcherInput;
use Raneomik\NetteMercure\Core\Publish\Broadcasters;
use Raneomik\NetteMercure\Core\Subscribe\JWTProviderInterface;
use Symfony\Component\Mercure\ProtocolVersion;

final class MercureExtension extends Extension
{
    private readonly Broadcasters $broadcasters;

    public function __construct(
        private readonly JWTProviderInterface $jwtProvider,
        private readonly BroadcastersLoader $broadcastersLoader,
        private readonly ConfiguredDataRegistry $configuredData,
    ) {
    }

    public function broadcasters(): Broadcasters
    {
        return $this->broadcasters ??= ($this->broadcastersLoader)();
    }

    #[\Override]
    /**
     * @return array{
     *     mercure: callable,
     *     mercureJWTToken: callable,
     * }
     */
    public function getFunctions(): array
    {
        return [
            'mercure' => $this->mercure(...),
            'mercureJWTToken' => $this->mercureJWTToken(...),
        ];
    }

    private function buildQuery(string $hub, array $topics, array $options): string
    {
        $hubInstance = $this->broadcasters()
            ->broadcasterHub($hub)
        ;

        $query = '';
        $separator = '?';
        if (ProtocolVersion::V1 === $hubInstance->getProtocolVersion()) {
            $normalized = MatcherInput::normalize($topics);
            foreach ($normalized as $matcherType => $patterns) {
                $paramName = 'exact' === $matcherType ? 'match' : 'match_'.rawurlencode($matcherType);
                foreach ($patterns as $pattern) {
                    $query .= $separator.$paramName.'='.rawurlencode($pattern);
                    $separator = '&';
                }
            }
        } else {
            foreach (MatcherInput::flattenToExactOrFail($topics) as $topic) {
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
            $query .= $separator.'authorization='.$this->mercureJWTToken(
                $options['subscribe'] ?? $hubData->subscribe,
                $options['additionalClaims'] ?? [],
                $hub,
            );
        }

        return $query;
    }

    /**
     * @param null|string|string[] $topics A topic or an array of topics to subscribe for. If this parameter is omitted or `null` is passed, the URL of the hub will be returned (useful for publishing in JavaScript).
     * @param array{
     *     subscribe?: string|string[],
     *     publish?: string|string[],
     *     additionalClaims?: array<string, mixed>,
     *     lastEventId?: string,
     *     hub?: string,
     *     addJwt?: bool // force jwt in "authorization" query parameter and override default configuration
     * } $options The options to pass to the JWT factory
     *
     * @return string The URL of the hub with the appropriate "topic" query parameters (if any)
     */
    private function mercure(array|string|null $topics = null, ?string $hub = null, array $options = []): string
    {
        $url = $this->broadcasters()
            ->broadcasterUrl($hub)
        ;

        return $url.$this->buildQuery(
            $hub,
            \is_string($topics) ? [$topics] : ($topics ?? []),
            $options,
        );
    }

    /**
     * @param string|string[] $subscribe
     * @param array<string, mixed> $additionClaims
     */
    private function mercureJWTToken(array|string $subscribe = ['*'], array $additionClaims = [], ?string $hub = null): string
    {
        return $this->jwtProvider->provide(
            $hub,
            $subscribe,
            $additionClaims,
        );
    }
}
