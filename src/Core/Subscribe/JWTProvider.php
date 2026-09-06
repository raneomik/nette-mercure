<?php

declare(strict_types=1);

namespace Raneomik\NetteMercure\Core\Subscribe;

use Lcobucci\JWT\Token\RegisteredClaims;
use Raneomik\NetteMercure\Bridge\Utils\GrantTopicNormalizer;
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
    public function provide(
        ?string $hubName = null,
        array|string $subscribedTopics = [],
        array $additionalClaims = [],
    ): string {
        $hubInstance = $this->registry->getHub($hubName);
        $tokenFactory = $hubInstance->getFactory();

        if (false === $tokenFactory instanceof TokenFactoryInterface) {
            throw new \InvalidArgumentException(
                \sprintf('The %s hub does not contain a token factory.', $hubName ? \sprintf('"%s"', $hubName) : 'default')
            );
        }

        $this->setCookieLifeDateTime($additionalClaims);

        return $tokenFactory->create(
            GrantTopicNormalizer::normalizeGrants($subscribedTopics),
            $additionalClaims,
        );
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
        if (null !== ($additionalClaims[RegisteredClaims::EXPIRATION_TIME] ?? null)) {
            $cookieLifetime = $additionalClaims[RegisteredClaims::EXPIRATION_TIME];
            $this->cookieDateTimeTtl = new \DateTimeImmutable(\sprintf('+%s seconds', $cookieLifetime));

            return;
        }

        $this->cookieDateTimeTtl = $additionalClaims[RegisteredClaims::EXPIRATION_TIME] = new \DateTimeImmutable(
            0 === $this->cookieLifetimeInSeconds
                ? '+1 hour'
                : \sprintf('+%s seconds', $cookieLifetime)
        );
    }
}
