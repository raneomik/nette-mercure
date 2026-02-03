<?php

declare(strict_types=1);

namespace Raneomik\NetteMercure\Core;

use Symfony\Component\Mercure\HubRegistry;
use Symfony\Component\Mercure\Jwt\TokenFactoryInterface;

final class JWTProvider
{
    private readonly int $cookieLifetimeInSeconds;

    private \DateTimeInterface $cookieLifetime;

    public function __construct(
        private readonly HubRegistry $registry,
        ?int $cookieLifetimeInSeconds = null,
    ) {
        $this->cookieLifetimeInSeconds = $cookieLifetimeInSeconds ?? (int) \ini_get('session.cookie_lifetime');
    }

    /**
     * Provides JWT token for the given hub.
     *
     * @param string|null $hubName the hub to generate the cookie for
     * @param string[]|string|null $subscribe a list of topics that the authorization cookie will allow subscribing to
     * @param string[]|string|null $publish a list of topics that the authorization cookie will allow publishing to
     * @param array<string, mixed> $additionalClaims an array of additional claims for the JWT token
     * @return string generated JWT token
     */
    public function provide(?string $hubName = null, string|array|null $subscribe = [], string|array|null $publish = [], array $additionalClaims = []): string
    {
        $hubInstance = $this->registry->getHub($hubName);
        $tokenFactory = $hubInstance->getFactory();

        if (false === $tokenFactory instanceof TokenFactoryInterface) {
            throw new \InvalidArgumentException(
                sprintf('The %s hub does not contain a token factory.', $hubName ? sprintf('"%s"', $hubName) : 'default')
            );
        }

        $cookieLifetime = $this->cookieLifetimeInSeconds;
        if (null !== ($additionalClaims['exp'] ?? null)) {
            $cookieLifetime = $additionalClaims['exp'];
        } else {
            $additionalClaims['exp'] = new \DateTimeImmutable(
                0 === $cookieLifetime
                    ? '+1 hour'
                    : sprintf('+%s seconds', $cookieLifetime)
            );
        }

        $this->cookieLifetime = new \DateTimeImmutable(sprintf('+%s seconds', $cookieLifetime));

        if (null !== $subscribe) {
            $subscribe = (array) $subscribe;
        }

        if (null !== $publish) {
            $publish = (array) $publish;
        }

        return $tokenFactory->create($subscribe, $publish, $additionalClaims);
    }

    public function hubUrl(?string $hubName = null): string
    {
        $hubInstance = $this->registry->getHub($hubName);

        return $hubInstance->getPublicUrl();
    }

    public function ttl(): \DateTimeInterface
    {
        return $this->cookieLifetime;
    }
}
