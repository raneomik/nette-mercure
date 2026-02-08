<?php

declare(strict_types=1);

namespace Tests\Fixtures\Dummies\Core;

use Symfony\Component\Mercure\Jwt\TokenFactoryInterface;

final readonly class DummyJwtFactory implements TokenFactoryInterface
{
    public function __construct(
        private string $secret,
    ) {
    }

    public function create(?array $subscribe = [], ?array $publish = [], array $additionalClaims = []): string
    {
        return \sprintf(
            'dummy-jwt-token-%s-%s-%s',
            $this->secret,
            implode('|', $subscribe ?? []),
            implode('|', $publish ?? []),
        );
    }
}
