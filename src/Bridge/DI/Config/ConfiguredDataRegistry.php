<?php

declare(strict_types=1);

namespace Raneomik\NetteMercure\Bridge\DI\Config;

/**
 * @implements \IteratorAggregate<string, ConfiguredData>
 */
final readonly class ConfiguredDataRegistry implements \IteratorAggregate
{
    /**
     * @param array<string, ConfiguredData> $configuredData
     */
    public function __construct(
        private iterable $configuredData,
    ) {
    }

    public function getConfiguration(?string $hubName = null): ConfiguredData
    {
        /** @var ConfiguredData $defaultData */
        $defaultData = array_first($this->configuredData);
        if (null === $hubName) {
            return $defaultData;
        }

        return $this->configuredData[$hubName] ?? $defaultData;
    }

    public function getIterator(): \Traversable
    {
        return new \ArrayIterator($this->configuredData);
    }
}
