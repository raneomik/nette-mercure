<?php

declare(strict_types=1);

namespace Raneomik\NetteMercure\Bridge\Tracy\Value;

use Raneomik\NetteMercure\Core\Publish\Broadcasters;
use Raneomik\NetteMercure\Core\Publish\LocalizedBroadcaster;
use Raneomik\NetteMercure\Core\Publish\Tracy\TraceableBroadcaster;
use Raneomik\NetteMercure\Core\Publish\Tracy\Value\BroadcastData;
use Raneomik\NetteMercure\Core\Publish\Tracy\Value\BroadcastStatus;

/**
 * @template-extends \ArrayIterator<string, HubDatum>
 */
final class HubData extends \ArrayIterator
{
    public float $totalDuration = 0;

    public float $totalMemory = 0;

    public float $totalSize = 0;

    public int $messageCount = 0;

    public int $errorCount = 0;

    public function __construct(
        Broadcasters $broadcasters
    ) {
        $data = [];
        foreach ($broadcasters as $name => $broadcaster) {
            // a localized hub traces each rendering inside its locale loop
            while ($broadcaster instanceof LocalizedBroadcaster) {
                $broadcaster = $broadcaster->decorated();
            }

            if (false === $broadcaster instanceof TraceableBroadcaster) {
                continue;
            }

            $this->totalDuration += $duration = $broadcaster->getDuration();
            $this->totalMemory += $memory = $broadcaster->getMemory();
            $this->totalSize += $size = $broadcaster->getSize();
            // drained: in worker mode the broadcaster outlives the request, the next bar must not show these again
            $broadcastData = $broadcaster->drain();
            $this->messageCount += \count($broadcastData);
            $this->errorCount += \count(array_filter(
                $broadcastData,
                static fn (BroadcastData $message): bool => BroadcastStatus::Error === $message->status,
            ));
            $data[$name] = new HubDatum(
                $name,
                $broadcastData,
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
        return 0 < $this->messageCount;
    }
}
