<?php

declare(strict_types=1);

namespace Raneomik\NetteMercure\Core\Publish\Tracy;

use Tracy\Debugger;

/**
 * @internal
 */
final class Metrics
{
    /**
     * @param array<string, array<string, float>> $pool
     */
    public function __construct(
        private array $pool = [],
    ) {
    }

    public function start(string $key): void
    {
        $this->pool['memory'][$key] = memory_get_usage();
        $this->pool['time'][$key] = Debugger::timer($key);
    }

    public function stop(string $key): void
    {
        $this->pool['memory'][$key] = memory_get_usage() - ($this->pool['memory'][$key] ?? 0);
        $this->pool['time'][$key] = Debugger::timer($key);
    }

    public function getMemory(string $key): float
    {
        return $this->pool['memory'][$key] ?? 0.0;
    }

    public function getDuration(string $key): float
    {
        return $this->pool['time'][$key] ?? 0.0;
    }
}
