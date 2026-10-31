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

    public float $totalSize = 0;

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
            $this->totalSize += $size = $broadcaster->getSize();
            $data[$name] = new HubDatum(
                $name,
                $broadcaster->broadcastData(),
                $duration,
                $memory,
                $size,
            );
        }

        /** @var array<string, HubDatum> $data */
        parent::__construct($data);
    }

    public function hasData(): bool
    {
        return 0 < $this->totalDuration && 0 < $this->totalMemory;
    }
}
