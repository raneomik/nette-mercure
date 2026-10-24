<?php

declare(strict_types=1);

namespace Raneomik\NetteMercure\Core\Subscribe;

use Symfony\Component\Mercure\Jwt\Grant;

/**
 * Manages the subscriber authorization cookie, as Symfony\Component\Mercure\Authorization does.
 */
interface AuthorizationInterface
{
    /**
     * Sets the subscriber authorization cookie for the given hub: check that the current user may access the topics first.
     *
     * @param array<array<string,string|string[]>>|array<string, string|string[]>|Grant[]|string|string[] $grants topics to subscribe to, or Grant instances
     * @param array<string, mixed> $additionalClaims an array of additional claims for the JWT
     * @param null|string $hub the hub to generate the cookie for
     */
    public function setCookie(array|string $grants = [], array $additionalClaims = [], ?string $hub = null): void;

    /**
     * Clears the subscriber authorization cookie for the given hub, e.g. on logout.
     */
    public function clearCookie(?string $hub = null): void;
}
