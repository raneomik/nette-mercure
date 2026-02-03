<?php

declare(strict_types=1);

namespace Raneomik\NetteMercure\Core\Response\Model;

final readonly class LinkData
{
    /**
     * Undocumented function
     */
    public function __construct(
        private string $hubUrl,
    ) {}

    public function getHubUrl(): string
    {
        return $this->hubUrl;
    }
}
