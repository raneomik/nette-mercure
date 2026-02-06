<?php

declare(strict_types=1);

namespace Tests\Fixtures\Dummies;

use Raneomik\NetteMercure\Core\Subscribe\JWTProviderInterface;
use Symfony\Component\Mercure\Jwt\TokenFactoryInterface;

final class DummyJwtProvider implements JWTProviderInterface
{
    private \DateTimeInterface $jwtTtl;

    /**
     * @param array<string,string> $hubUrls
     */
    public function __construct(
        private readonly array $hubUrls = [
            'default' => 'https://hub.example.com',
            'null' => '',
        ],
        private readonly TokenFactoryInterface $tokenFactory = new DummyJwtFactory('test-provider-token'),
    ) {
    }

    public function provide(?string $hubName = null, array|string|null $subscribe = [], array|string|null $publish = [], array $additionalClaims = []): string
    {
        $this->jwtTtl = new \DateTimeImmutable(\sprintf('+%s seconds', $additionalClaims['exp'] ?? 3600));

        return $this->tokenFactory->create((array) $subscribe, (array) $publish, $additionalClaims);
    }

    public function hubUrl(?string $hubName = null): string
    {
        return $this->hubUrls[$hubName ?? 'default'];
    }

    public function ttl(): \DateTimeInterface
    {
        return $this->jwtTtl;
    }
}
