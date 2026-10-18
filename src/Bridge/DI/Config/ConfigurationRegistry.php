<?php

declare(strict_types=1);

namespace Raneomik\NetteMercure\Bridge\DI\Config;

final readonly class ConfigurationRegistry
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
}
