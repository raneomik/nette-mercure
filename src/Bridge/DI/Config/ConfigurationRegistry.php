<?php

declare(strict_types=1);

namespace Raneomik\NetteMercure\Bridge\DI\Config;

/**
 * @implements \IteratorAggregate<string, Configuration>
 */
final readonly class ConfigurationRegistry implements \IteratorAggregate
{
    /**
     * @param array<string, Configuration> $configuredData
     */
    public function __construct(
        private iterable $configuredData,
    ) {
    }

    public function getConfiguration(?string $hubName = null): Configuration
    {
        /** @var Configuration $defaultData */
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
