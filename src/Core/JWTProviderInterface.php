<?php

declare(strict_types=1);

namespace Raneomik\NetteMercure\Core;

interface JWTProviderInterface
{
    /**
     * Provides JWT token for the given hub.
     *
     * @param string|null $hubName the hub to generate the cookie for
     * @param string[]|string|null $subscribe a list of topics that the authorization cookie will allow subscribing to
     * @param string[]|string|null $publish a list of topics that the authorization cookie will allow publishing to
     * @param array<string, mixed> $additionalClaims an array of additional claims for the JWT token
     * @return string generated JWT token
     */
    public function provide(?string $hubName = null, string|array|null $subscribe = [], string|array|null $publish = [], array $additionalClaims = []): string;

    public function hubUrl(?string $hubName = null): string;

    public function ttl(): \DateTimeInterface;
}
