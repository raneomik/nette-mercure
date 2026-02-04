<?php

declare(strict_types=1);

namespace Raneomik\NetteMercure\Bridge\Utils;

final readonly class DefinedData
{
    /**
     * @param string[] $subscribe
     * @param string[] $publish
     */
    public function __construct(
        public string $hubUrl,
        public array $subscribe,
        public array $publish,
        public bool $disableCookie,
    ) {}
}
