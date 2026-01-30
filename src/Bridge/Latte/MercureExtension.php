<?php

declare(strict_types=1);

namespace Raneomik\NetteMercure\Bridge\Latte;

use Latte\Extension;
use Raneomik\NetteMercure\Bridge\Utils\BroadcastersLoader;
use Raneomik\NetteMercure\Core\Broadcasters;

final class MercureExtension extends Extension
{
	private readonly Broadcasters $broadcasters;

	public function __construct(
	    private readonly BroadcastersLoader $broadcastersLoader,
	) {}

	public function broadcasters(): Broadcasters
	{
		return $this->broadcasters ??= ($this->broadcastersLoader)();
	}

	public function getFunctions(): array
	{
		return [
		    'mercure' => $this->mercure(...),
		];
	}

	/**
	 * @param string|string[]|null                                                                                                                       $topics  A topic or an array of topics to subscribe for. If this parameter is omitted or `null` is passed, the URL of the hub will be returned (useful for publishing in JavaScript).
	 * @param array{subscribe?: string[]|string, publish?: string[]|string, additionalClaims?: array<string, mixed>, lastEventId?: string, hub?: string} $options The options to pass to the JWT factory
	 *
	 * @return string The URL of the hub with the appropriate "topic" query parameters (if any)
	 */
	private function mercure(string|array|null $topics = null, ?string $hub = null, array $options = []): string
	{
		$url = $this->broadcasters()->broadcasterUrl($hub);
		if ($topics !== null) {
			// We cannot use http_build_query() because this method doesn't support generating multiple query parameters with the same name without the [] suffix
			$separator = '?';
			foreach ((array) $topics as $topic) {
				$url .= $separator . 'topic=' . rawurlencode($topic);
				if ($separator === '?') {
					$separator = '&';
				}
			}
		}

		if ('' !== ($options['lastEventId'] ?? '')) {
			$encodedLastEventId = rawurlencode($options['lastEventId']);
			$url .= '&lastEventID=' . $encodedLastEventId;
		}

		return $url;
	}
}
