<?php

declare(strict_types=1);

namespace Tests\Fixtures\Dummies\Core\Subscribe;

use Raneomik\NetteMercure\Core\Subscribe\JWTProviderInterface;
use Symfony\Component\Mercure\Jwt\TokenFactoryInterface;
use Tests\Fixtures\Dummies\Core\DummyJwtFactory;

/**
 * @testCase
 */
final class DummyJwtProvider implements JWTProviderInterface
{
    private \DateTimeInterface $jwtTtl;

    public function __construct(
        private readonly TokenFactoryInterface $tokenFactory = new DummyJwtFactory('test-provider-token'),
    ) {
    }

    public function provide(?string $hubName = null, array|string|null $subscribedTopics = [], array $additionalClaims = []): string
    {
        $this->jwtTtl = new \DateTimeImmutable(\sprintf('+%s seconds', $additionalClaims['exp'] ?? 3600));

        return $this->tokenFactory->create((array) $subscribedTopics, null, $additionalClaims);
    }

    public function ttl(): \DateTimeInterface
    {
        return $this->jwtTtl;
    }
}
