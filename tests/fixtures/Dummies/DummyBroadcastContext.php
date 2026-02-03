<?php

declare(strict_types=1);

namespace Tests\Fixtures\Dummies;

use Raneomik\NetteMercure\Core\Response\BroadcastContextInterface;

final class DummyBroadcastContext implements BroadcastContextInterface
{
    public function setHubContextData(string $hubUrl, ?string $hubName = null, array $subscribe = [], array $publish = [], array $additionalClaims = []): void
    {
    }
}
