<?php

declare(strict_types=1);

namespace Tests\Fixtures\Dummies\Core\Subscribe;

use Raneomik\NetteMercure\Bridge\Utils\GrantTopicNormalizer;
use Raneomik\NetteMercure\Core\Subscribe\JWTProviderInterface;
use Raneomik\NetteMercure\Core\Subscribe\Value\JWTToken;
use Symfony\Component\Mercure\Jwt\TokenFactoryInterface;
use Tests\Fixtures\Dummies\Core\DummyJwtFactory;

final readonly class DummyJwtProvider implements JWTProviderInterface
{
    public function __construct(
        private TokenFactoryInterface $tokenFactory = new DummyJwtFactory('test-provider-token'),
    ) {
    }

    public function provide(?string $hubName = null, array|string $subscribedTopics = [], array $additionalClaims = []): JWTToken
    {
        return new JWTToken(
            token: $this->tokenFactory->create(
                GrantTopicNormalizer::normalizeGrants($subscribedTopics),
                $additionalClaims,
            ),
            ttl: new \DateTimeImmutable(\sprintf('+%s seconds', $additionalClaims['exp'] ?? 3600)),
        );
    }
}
