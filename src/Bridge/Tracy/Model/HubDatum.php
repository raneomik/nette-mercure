<?php

declare(strict_types=1);

namespace Nette\Mercure\Bridge\Tracy\Model;

use Nette\Mercure\Tracy\Model\BroadcastData;

final readonly class HubDatum
{
	/**
	 * @param BroadcastData[] $broadcastData
	 */
	public function __construct(
		public string $hubName,
		public string $hubUrl,
		public array $broadcastData,
		public float $duration,
		public float $memory,
	) {}

	public function messageCount(): int
	{
		return count($this->broadcastData);
	}
}
