<?php

declare(strict_types=1);

namespace Raneomik\NetteMercure\Bridge\Utils;

use Raneomik\NetteMercure\Core\Publish\Broadcasters;

final readonly class BroadcastersLoader
{
    private object $broadcastersLoader;

    public function __construct(
        callable $broadcastersLoader,
    ) {
        // @phpstan-ignore-next-line - on purpose
        $this->broadcastersLoader = $broadcastersLoader;
    }

    public function __invoke(): Broadcasters
    {
        return ($this->broadcastersLoader)();
    }
}
