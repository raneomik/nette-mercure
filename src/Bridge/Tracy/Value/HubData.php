<?php

declare(strict_types=1);

namespace Raneomik\NetteMercure\Bridge\Tracy\Value;

use Raneomik\NetteMercure\Core\Publish\Broadcasters;
use Raneomik\NetteMercure\Core\Publish\Tracy\TraceableBroadcaster;

/**
 * @template-extends \ArrayIterator<string, HubDatum>
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
            $data[$name] = new HubDatum(
                $name,
                $broadcaster->broadcasterUrl(),
                $broadcaster->getMessageData(),
                $duration,
                $memory,
            );
        }

        /** @var array<string, HubDatum> $data */
        parent::__construct($data);
    }
}
