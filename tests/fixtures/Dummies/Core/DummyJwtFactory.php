<?php

declare(strict_types=1);

namespace Tests\Fixtures\Dummies\Core;

use Symfony\Component\Mercure\Jwt\Grant;
use Symfony\Component\Mercure\Jwt\TokenFactoryInterface;

final readonly class DummyJwtFactory implements TokenFactoryInterface
{
    public function __construct(
        private string $secret,
    ) {
    }

    public function create(array $grants = [], array $additionalClaims = []): string
    {
        $subscribedGrants = array_filter(
            $grants,
            static fn (Grant $grant): bool => \in_array(Grant::ACTION_SUBSCRIBE, $grant->actions, true),
        );

        return \sprintf(
            'dummy-jwt-token-%s-%s-',
            $this->secret,
            implode('|', ...array_map(static fn (Grant $grant): array => $grant->topics, $subscribedGrants)),
        );
    }
}
