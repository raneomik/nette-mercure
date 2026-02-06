<?php

declare(strict_types=1);

namespace Raneomik\NetteMercure\Core\Subscribe;

use Symfony\Component\Mercure\HubRegistry;
use Symfony\Component\Mercure\Jwt\TokenFactoryInterface;

final class JWTProvider implements JWTProviderInterface
{
    private readonly int $cookieLifetimeInSeconds;

    private \DateTimeInterface $cookieDateTimeTtl;

    public function __construct(
        private readonly HubRegistry $registry,
        ?int $cookieLifetimeInSeconds = null,
    ) {
        $this->cookieLifetimeInSeconds = $cookieLifetimeInSeconds ?? (int) \ini_get('session.cookie_lifetime');
    }

    #[\Override]
    public function provide(?string $hubName = null, array|string|null $subscribe = [], array|string|null $publish = [], array $additionalClaims = []): string
    {
        $hubInstance = $this->registry->getHub($hubName);
        $tokenFactory = $hubInstance->getFactory();

        if (false === $tokenFactory instanceof TokenFactoryInterface) {
            throw new \InvalidArgumentException(
                \sprintf('The %s hub does not contain a token factory.', $hubName ? \sprintf('"%s"', $hubName) : 'default')
            );
        }

        $this->setCookieLifeDateTime($additionalClaims);

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
        return $this->cookieDateTimeTtl;
    }

    /**
     * @param array<string, mixed> $additionalClaims
     */
    private function setCookieLifeDateTime(array &$additionalClaims): void
    {
        $cookieLifetime = $this->cookieLifetimeInSeconds;
        if (null !== ($additionalClaims['exp'] ?? null)) {
            $cookieLifetime = $additionalClaims['exp'];
            $this->cookieDateTimeTtl = new \DateTimeImmutable(\sprintf('+%s seconds', $cookieLifetime));

            return;
        }

        $this->cookieDateTimeTtl = $additionalClaims['exp'] = new \DateTimeImmutable(
            0 === $this->cookieLifetimeInSeconds
                ? '+1 hour'
                : \sprintf('+%s seconds', $cookieLifetime)
        );
    }
}
