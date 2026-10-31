<?php

declare(strict_types=1);

namespace Raneomik\NetteMercure\Bridge\DI\Config;

/**
 * @implements \IteratorAggregate<string, Configuration>
 */
final readonly class ConfigurationRegistry implements \IteratorAggregate
{
    /**
     * @param array<string, Configuration> $configurations
     */
    public function __construct(
        private iterable $configurations,
    ) {
    }

    public function getConfiguration(?string $hubName = null): Configuration
    {
        if (null === $hubName) {
            /** @var string $firstKey */
            $firstKey = array_key_first($this->configurations);

            return $this->configurations[$firstKey];
        }

        return $this->configurations[$hubName]
            ?? throw new \InvalidArgumentException(sprintf('"%s" is not a known hub', $hubName))
        ;
    }

    public function getIterator(): \Traversable
    {
        yield from $this->configurations;
    }
}
