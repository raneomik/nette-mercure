<?php

declare(strict_types=1);

namespace Tests\Fixtures\Dummies\Core\Subscribe;

use Raneomik\NetteMercure\Core\Subscribe\JWTProviderInterface;
use Symfony\Component\Mercure\Jwt\Grant;
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

    public function provide(?string $hubName = null, array|string $subscribedTopics = [], array $additionalClaims = []): string
    {
        $this->jwtTtl = new \DateTimeImmutable(\sprintf('+%s seconds', $additionalClaims['exp'] ?? 3600));

        return $this->tokenFactory->create(
            [
                new Grant(
                    [Grant::ACTION_SUBSCRIBE],
                    \is_array($subscribedTopics) ? $subscribedTopics : [$subscribedTopics],
                ),
            ],
            $additionalClaims,
        );
    }

    public function ttl(): \DateTimeInterface
    {
        return $this->jwtTtl;
    }
}
