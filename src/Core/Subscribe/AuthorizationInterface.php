<?php

declare(strict_types=1);

namespace Raneomik\NetteMercure\Core\Subscribe;

/**
 * Manages the "mercureAuthorization" cookies.
 */
interface AuthorizationInterface
{
    /**
     * Creates mercureAuthorization cookie for the given hub.
     *
     * @param null|string|string[] $subscribe a list of topics that the authorization cookie will allow subscribing to
     * @param null|string|string[] $publish a list of topics that the authorization cookie will allow publishing to
     * @param array<string, mixed> $additionalClaims an array of additional claims for the JWT
     * @param null|string $hub the hub to generate the cookie for
     */
    public function createCookie(array|string|null $subscribe = [], array|string|null $publish = [], array $additionalClaims = [], ?string $hub = null): void;
}
