<?php

declare(strict_types=1);

namespace Raneomik\NetteMercure\Core\Subscribe;

use Nette\Http\FetchSite;
use Nette\Http\IRequest;
use Nette\Http\IResponse;
use Nette\Http\SameSite;
use Raneomik\NetteMercure\Bridge\Utils\ConfiguredDataRegistry;

final readonly class Authorization implements AuthorizationInterface
{
    public const string COOKIE_NAME = 'mercureAuthorization';

    public function __construct(
        private JWTProviderInterface $jwtProvider,
        private IRequest $request,
        private IResponse $response,
        private ConfiguredDataRegistry $config,
        private ?SameSite $cookieSameSite = SameSite::Lax,
    ) {
    }

    public function createCookieFromCurrentRequest(): void
    {
        $hub = $this->request->getQuery('hub') ?? $this->request->getQuery('hubName');
        $hubConfig = $this->config->getConfiguration($hub);

        if (false === $hubConfig->useCookie) {
            return;
        }

        $topics = $this->request->getQuery('topics') ?? ['*'];
        $additionalClaims = $this->request->getQuery('claims') ?? [];

        $this->createCookie(
            $topics,
            $additionalClaims,
            $hub,
        );
    }

    public function createCookie(array|string $subscribedTopics = [], array $additionalClaims = [], ?string $hub = null): void
    {
        $token = $this->jwtProvider->provide($hub, $subscribedTopics, $additionalClaims);

        $hubConfig = $this->config->getConfiguration($hub);

        /** @var array{
         * scheme?: string,
         * host?: string,
         * port?: int,
         * path?: string,
         * } $urlComponents */
        $urlComponents = parse_url($hubConfig->hubUrl);

        $this->response->setCookie(
            name: self::COOKIE_NAME,
            value: $token,
            expire: $this->jwtProvider->ttl(),
            path: $urlComponents['path'] ?? '/',
            domain: $this->getCookieDomain($urlComponents['host'] ?? null),
            secure: 'http' !== strtolower($urlComponents['scheme'] ?? 'https'),
            httpOnly: true,
            sameSite: $this->cookieSameSite->value,  // @phpstan-ignore-line
        );
    }

    private function getCookieDomain(?string $hubDomain = null): ?string
    {
        if (null === $hubDomain) {
            return null;
        }

        $hubDomain = strtolower($hubDomain);
        $host = strtolower($this->request->getUrl()->getHost());

        if ($this->request->isFrom(FetchSite::SameSite)
            || $hubDomain === $host
        ) {
            return null;
        }

        if (str_ends_with($hubDomain, '.'.$host)) {
            return $host;
        }

        $hostSegments = explode('.', $host);
        for ($i = 0, $length = \count($hostSegments) - 1; $i < $length; ++$i) {
            $currentDomain = implode('.', \array_slice($hostSegments, $i));
            $target = '.'.$currentDomain;
            if ($currentDomain === $hubDomain || str_ends_with($hubDomain, $target)) {
                return $target;
            }
        }

        throw new \RuntimeException(\sprintf('Unable to create authorization cookie for a hub on the different second-level domain "%s".', $hubDomain));
    }
}
