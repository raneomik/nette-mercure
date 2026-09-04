<?php

declare(strict_types=1);

namespace Raneomik\NetteMercure\Core\Subscribe;

use Lcobucci\JWT\Token\RegisteredClaims;
use Symfony\Component\Mercure\Jwt\Grant;

interface JWTProviderInterface
{
    public function ttl(): \DateTimeInterface;

    /**
     * Provides JWT token for the given hub.
     *
     * @param null|string $hubName the hub to generate the cookie for
     * @param array<array<string,string|string[]>>|array<string, string|string[]>|Grant[]|string|string[] $subscribedTopics a list of topics that the authorization cookie will allow subscribing to
     * @param array<string, mixed> $additionalClaims an array of additional claims for the JWT token @see RegisteredClaims
     *
     * @return string generated JWT token
     */
    public function provide(?string $hubName = null, array|string $subscribedTopics = [], array $additionalClaims = []): string;
}
