<?php

declare(strict_types=1);

namespace Raneomik\NetteMercure\Core\Subscribe;

use Lcobucci\JWT\Token\RegisteredClaims;
use Raneomik\NetteMercure\Bridge\DI\Config\ConfigurationRegistry;
use Raneomik\NetteMercure\Bridge\Utils\GrantTopicNormalizer;
use Symfony\Component\Mercure\HubRegistry;
use Symfony\Component\Mercure\Jwt\TokenFactoryInterface;

final class JWTProvider implements JWTProviderInterface
{
    private ?\DateTimeInterface $cookieDateTimeTtl = null;

    public function __construct(
        private readonly HubRegistry $registry,
        private readonly ConfigurationRegistry $config,
    ) {
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

        $this->setCookieLifeDateTime(
            $additionalClaims,
            $this->config->getConfiguration($hubName)->lifetime,
        );

        return $tokenFactory->create(
            GrantTopicNormalizer::normalizeGrants($subscribedTopics),
            $additionalClaims,
        );
    }

    public function ttl(): ?\DateTimeInterface
    {
        return $this->cookieDateTimeTtl;
    }

    /**
     * @param array<string, mixed> $additionalClaims
     */
    private function setCookieLifeDateTime(array &$additionalClaims, ?int $lifetime): void
    {
        $exp = $additionalClaims[RegisteredClaims::EXPIRATION_TIME] ?? null;
        if ($exp instanceof \DateTimeInterface) {
            $this->cookieDateTimeTtl = $exp;

            return;
        }

        // a scalar "exp" is a lifetime in seconds: the token needs a date, or "1800" would mean 1970
        $seconds = null !== $exp ? (int) $exp : $lifetime;
        if (null === $seconds) {
            // "lifetime: null": non-expiring token, session cookie
            $this->cookieDateTimeTtl = null;

            return;
        }

        // same resolution as the token factories, so the cookie and the token expire together
        $seconds = 0 === $seconds ? ((int) \ini_get('session.cookie_lifetime') ?: 3600) : $seconds;
        $this->cookieDateTimeTtl = $additionalClaims[RegisteredClaims::EXPIRATION_TIME] = new \DateTimeImmutable(
            \sprintf('+%d seconds', $seconds),
        );
    }
}
