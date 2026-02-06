<?php

declare(strict_types=1);

namespace Raneomik\NetteMercure\Core\Subscribe;

interface JWTProviderInterface
{
    /**
     * Provides JWT token for the given hub.
     *
     * @param null|string $hubName the hub to generate the cookie for
     * @param null|string|string[] $subscribe a list of topics that the authorization cookie will allow subscribing to
     * @param null|string|string[] $publish a list of topics that the authorization cookie will allow publishing to
     * @param array<string, mixed> $additionalClaims an array of additional claims for the JWT token
     *
     * @return string generated JWT token
     */
    public function provide(?string $hubName = null, array|string|null $subscribe = [], array|string|null $publish = [], array $additionalClaims = []): string;

    public function hubUrl(?string $hubName = null): string;

    public function ttl(): \DateTimeInterface;
}
