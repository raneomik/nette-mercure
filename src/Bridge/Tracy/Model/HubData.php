<?php

declare(strict_types=1);

namespace Raneomik\NetteMercure\Bridge\Tracy\Model;

use Raneomik\NetteMercure\Core\Broadcasters;
use Raneomik\NetteMercure\Tracy\TraceableBroadcaster;

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
			if ($broadcaster instanceof TraceableBroadcaster === false) {
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
