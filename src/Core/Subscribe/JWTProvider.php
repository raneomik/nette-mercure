<?php

declare(strict_types=1);

namespace Raneomik\NetteMercure\Core\Subscribe;

use Lcobucci\JWT\Token\RegisteredClaims;
use Raneomik\NetteMercure\Bridge\DI\Config\ConfigurationRegistry;
use Raneomik\NetteMercure\Bridge\Utils\GrantTopicNormalizer;
use Raneomik\NetteMercure\Core\Subscribe\Value\JWTToken;
use Symfony\Component\Mercure\HubRegistry;
use Symfony\Component\Mercure\Jwt\TokenFactoryInterface;

final readonly class JWTProvider implements JWTProviderInterface
{
    public function __construct(
        private HubRegistry $registry,
        private ConfigurationRegistry $config,
    ) {
    }

    #[\Override]
    public function provide(
        ?string $hubName = null,
        array|string $subscribedTopics = [],
        array $additionalClaims = [],
    ): JWTToken {
        $hubInstance = $this->registry->getHub($hubName);
        $tokenFactory = $hubInstance->getFactory();

        if (false === $tokenFactory instanceof TokenFactoryInterface) {
            throw new \InvalidArgumentException(
                \sprintf('The %s hub does not contain a token factory.', $hubName ? \sprintf('"%s"', $hubName) : 'default')
            );
        }

        $originalClaims = $additionalClaims;
        $ttl = $this->resolveTtl(
            $additionalClaims,
            $this->config->getConfiguration($hubName)->lifetime,
        );

        return new JWTToken(
            token: $tokenFactory->create(
                GrantTopicNormalizer::normalizeGrants($subscribedTopics),
                $additionalClaims,
            ),
            ttl: $ttl,
            extraClaims: $originalClaims,
        );
    }

    /**
     * Resolves the cookie TTL from claims or configured lifetime, and injects a DateTimeImmutable
     * exp into $additionalClaims so the token and the cookie expire together.
     *
     * @param array<string, mixed> $additionalClaims
     */
    private function resolveTtl(array &$additionalClaims, ?int $lifetime): ?\DateTimeInterface
    {
        $exp = $additionalClaims[RegisteredClaims::EXPIRATION_TIME] ?? null;
        if ($exp instanceof \DateTimeInterface) {
            return $exp;
        }

        // a scalar "exp" is a lifetime in seconds: the token needs a date, or "1800" would mean 1970
        $seconds = null !== $exp ? (int) $exp : $lifetime;
        if (null === $seconds) {
            // "lifetime: null": non-expiring token, session cookie
            return null;
        }

        // same resolution as the token factories, so the cookie and the token expire together
        $seconds = 0 === $seconds ? ((int) \ini_get('session.cookie_lifetime') ?: 3600) : $seconds;

        return $additionalClaims[RegisteredClaims::EXPIRATION_TIME] = new \DateTimeImmutable(
            \sprintf('+%d seconds', $seconds),
        );
    }
}
