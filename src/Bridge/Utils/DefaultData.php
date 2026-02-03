<?php

declare(strict_types=1);

namespace Raneomik\NetteMercure\Bridge\Utils;

final readonly class DefaultData
{
    /**
     * @param string|string[]|null $subscribe
     * @param string|string[]|null $publish
     * @param array<string,mixed> $additionalClaims
     */
    public function __construct(
        private null|string $hubUrl,
        private null|string|array $subscribe,
        private null|string|array $publish,
        private array $additionalClaims = [],
    ) {}

    public function getHubUrl(): ?string
    {
        return $this->hubUrl;
    }

    /**
     * @return string[]
     */
    public function getSubscribe(): array
    {
        return (array) $this->subscribe;
    }

    /**
     * @return string[]
     */
    public function getPublish(): array
    {
        return (array) $this->publish;
    }

    /**
     * @return array<string,mixed>
     */
    public function getAdditionalClaims(): array
    {
        return $this->additionalClaims;
    }
}
