<?php

declare(strict_types=1);

namespace Raneomik\NetteMercure\Core\Response;

/**
 * Manages the "mercureAuthorization" cookies.
 */
interface AuthorizationInterface
{
    /**
     * Creates mercureAuthorization cookie for the given hub.
     *
     * @param string[]|string|null $subscribe        a list of topics that the authorization cookie will allow subscribing to
     * @param string[]|string|null $publish          a list of topics that the authorization cookie will allow publishing to
     * @param array<string, mixed> $additionalClaims an array of additional claims for the JWT
     * @param string|null          $hub              the hub to generate the cookie for
     */
    public function createCookie(string|array|null $subscribe = [], string|array|null $publish = [], array $additionalClaims = [], ?string $hub = null): void;
}
