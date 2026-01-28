<?php

declare(strict_types=1);

namespace Nette\Mercure\Bridge\Tracy\Model;

use Nette\Mercure\Core\Broadcasters;
use Nette\Mercure\Tracy\TraceableBroadcaster;

/**
 * @template-extends \ArrayIterator<int, HubDatum>
 */
final class HubData extends \ArrayIterator
{
	public float $totalDuration = 0;

	public float $totalMemory = 0;

	public function __construct(
		Broadcasters $broadcasters
	) {
		$data = [];
		foreach ($broadcasters as $name => $broadcaster) {
			if (false === $broadcaster instanceof TraceableBroadcaster) {
				continue;
			}

			$this->totalDuration += $duration = $broadcaster->getDuration();
			$this->totalMemory += $memory = $broadcaster->getMemory();

			$data[] = new HubDatum(
				$name,
				$broadcaster->broadcasterUrl(),
				$broadcaster->getMessageData(),
				$duration,
				$memory,
			);
		}

		parent::__construct($data);
	}
}
