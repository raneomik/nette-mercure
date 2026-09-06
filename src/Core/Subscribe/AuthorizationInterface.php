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
     * @param string|string[] $subscribedTopics a list of topics that the authorization cookie will allow subscribing to
     * @param array<string, mixed> $additionalClaims an array of additional claims for the JWT
     * @param null|string $hub the hub to generate the cookie for
     */
    public function createCookie(array|string $subscribedTopics = [], array $additionalClaims = [], ?string $hub = null): void;
}
