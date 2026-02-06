<?php

declare(strict_types=1);

namespace Raneomik\NetteMercure\Bridge\Tracy\Value;

use Raneomik\NetteMercure\Core\Publish\Tracy\Value\BroadcastData;

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
    ) {
    }

    public function messageCount(): int
    {
        return \count($this->broadcastData);
    }
}
