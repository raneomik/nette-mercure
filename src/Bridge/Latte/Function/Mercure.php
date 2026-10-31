<?php

declare(strict_types=1);

namespace Raneomik\NetteMercure\Bridge\Latte\Function;

use Raneomik\NetteMercure\Bridge\Utils\SubscriptionQuery;
use Raneomik\NetteMercure\Core\Publish\Broadcasters;

/**
 * @phpstan-type MercureOptions array{
 *       lastEventId?: string,
 *       hub?: string,
 *   }
 */
final readonly class Mercure
{
    private Broadcasters $broadcasters;

    private function __construct(
        private \Closure $broadcastersLoader,
    ) {
    }

    /**
     * @param null|array<string,string[]>|string|string[] $topics A topic or an array of topics to subscribe for. If this parameter is omitted or `null` is passed, the URL of the hub will be returned (useful for publishing in JavaScript).
     * @param MercureOptions $options
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

    public static function build(\Closure $broadcastersLoader): self
    {
        return new self($broadcastersLoader);
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
            $this->broadcasters()
                ->broadcasterHub($hub)
                ->getProtocolVersion(),
        );

        if ('' !== ($options['lastEventId'] ?? '')) {
            $params[] = 'lastEventID=' . rawurlencode($options['lastEventId']);
        }

        return SubscriptionQuery::build($params);
    }
}
