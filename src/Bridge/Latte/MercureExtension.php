<?php

declare(strict_types=1);

namespace Raneomik\NetteMercure\Bridge\Latte;

use Latte\Extension;
use Raneomik\NetteMercure\Bridge\Utils\BroadcastersLoader;
use Raneomik\NetteMercure\Bridge\Utils\ConfiguredDataRegistry;
use Raneomik\NetteMercure\Core\Publish\Broadcasters;
use Raneomik\NetteMercure\Core\Subscribe\JWTProviderInterface;

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
    public function getFunctions(): array
    {
        return [
            'mercure' => $this->mercure(...),
            'mercureJWTToken' => $this->mercureJWTToken(...),
        ];
    }

    /**
     * @param null|string|string[] $topics A topic or an array of topics to subscribe for. If this parameter is omitted or `null` is passed, the URL of the hub will be returned (useful for publishing in JavaScript).
     * @param array{subscribe?: string|string[], publish?: string|string[], additionalClaims?: array<string, mixed>, lastEventId?: string, hub?: string, addJwt?: bool} $options The options to pass to the JWT factory
     *
     * @return string The URL of the hub with the appropriate "topic" query parameters (if any)
     */
    private function mercure(array|string|null $topics = null, ?string $hub = null, array $options = []): string
    {
        $url = $this->broadcasters()->broadcasterUrl($hub);
        if (null !== $topics) {
            // We cannot use http_build_query() because this method doesn't support generating multiple query parameters with the same name without the [] suffix
            $separator = '?';
            foreach ((array) $topics as $topic) {
                $url .= $separator.'topic='.rawurlencode($topic);
                if ('?' === $separator) {
                    $separator = '&';
                }
            }
        }

        if ('' !== ($options['lastEventId'] ?? '')) {
            $encodedLastEventId = rawurlencode($options['lastEventId']);
            $url .= '&lastEventID='.$encodedLastEventId;
        }

        $hubData = $this->configuredData->getConfiguration($hub);

        if (false !== ($options['addJwt'] ?? false)) {
            $url .= '&authorization='.$this->mercureJWTToken(
                $options['subscribe'] ?? $hubData->subscribe ?? ['*'],
                $options['publish'] ?? $hubData->publish ?? ['*'],
                $hub,
            );
        }

        return $url;
    }

    /**
     * @param null|string|string[] $subscribe
     * @param null|string|string[] $publish
     */
    private function mercureJWTToken(array|string|null $subscribe = ['*'], array|string|null $publish = ['*'], ?string $hub = null): string
    {
        return $this->jwtProvider->provide(
            $hub,
            (array) $subscribe,
            (array) $publish,
        );
    }
}
