<?php

declare(strict_types=1);

namespace Raneomik\NetteMercure\Core\Subscribe;

interface JWTProviderInterface
{
    /**
     * Provides JWT token for the given hub.
     *
     * @param null|string $hubName the hub to generate the cookie for
     * @param string|string[] $subscribedTopics a list of topics that the authorization cookie will allow subscribing to
     * @param array<string, mixed> $additionalClaims an array of additional claims for the JWT token
     *
     * @return string generated JWT token
     */
    public function provide(?string $hubName = null, array|string $subscribedTopics = [], array $additionalClaims = []): string;

    public function ttl(): \DateTimeInterface;
}
