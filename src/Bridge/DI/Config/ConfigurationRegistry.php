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
        /** @var string $first */
        $first = array_key_first($this->configurations);

        return $this->configurations[$hubName ?? $first]
            ?? $this->configurations[$first]
        ;
    }

    public function getIterator(): \Traversable
    {
        yield from $this->configurations;
    }
}
