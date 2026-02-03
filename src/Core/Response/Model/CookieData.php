<?php

declare(strict_types=1);

namespace Raneomik\NetteMercure\Core\Response\Model;

final readonly class CookieData
{
    /**
     * @param string[] $subscribe
     * @param string[] $publish
     * @param array<string,mixed> $additionalClaims
     */
    public function __construct(
        private array $subscribe,
        private array $publish,
        private array $additionalClaims = [],
    ) {}

    /**
     * @return string[]
     */
    public function getSubscribe(): array
    {
        return $this->subscribe;
    }

    /**
     * @return string[]
     */
    public function getPublish(): array
    {
        return $this->publish;
    }

    /**
     * @return array<string,mixed>
     */
    public function getAdditionalClaims(): array
    {
        return $this->additionalClaims;
    }
}
