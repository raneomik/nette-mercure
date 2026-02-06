<?php

declare(strict_types=1);

namespace Raneomik\NetteMercure\Core\Response;

use Nette\Http\IRequest;
use Nette\Http\IResponse;
use Raneomik\NetteMercure\Core\JWTProviderInterface;

final readonly class Authorization implements AuthorizationInterface
{
    public const string COOKIE_NAME = 'mercureAuthorization';

    public function __construct(
        private JWTProviderInterface $jwtProvider,
        private IRequest $request,
        private IResponse $response,
        private ?string $cookieSameSite = IResponse::SameSiteLax,
    ) {
    }

    public function createCookie(string|array|null $subscribe = [], string|array|null $publish = [], array $additionalClaims = [], ?string $hub = null): void
    {
        $token = $this->jwtProvider->provide($hub, $subscribe, $publish, $additionalClaims);

        $url = $this->jwtProvider->hubUrl($hub);
        /** @var array{
         * scheme?: string,
         * host?: string,
         * port?: int,
         * path?: string,
         * } $urlComponents */
        $urlComponents = parse_url($url);

        $this->response->setCookie(
            name: self::COOKIE_NAME,
            value: $token,
            expire: $this->jwtProvider->ttl(),
            path: $urlComponents['path'] ?? '/',
            domain: $this->getCookieDomain( $urlComponents['host'] ?? null),
            secure: 'http' !== strtolower($urlComponents['scheme'] ?? 'https'),
            httpOnly: true,
            sameSite: $this->cookieSameSite,  //@phpstan-ignore-line
        );
    }

    private function getCookieDomain(?string $hubDomain = null): ?string
    {
        if (null === $hubDomain) {
            return null;
        }

        $hubDomain = strtolower($hubDomain);
        $host = strtolower($this->request->getUrl()->getHost());

        if ($this->request->isFrom(['same-site', 'same-origin'])
            || $hubDomain === $host
        ) {
            return null;
        }

        if (str_ends_with($hubDomain, '.' . $host)) {
            return $host;
        }

        $hostSegments = explode('.', $host);
        for ($i = 0, $length = \count($hostSegments) - 1; $i < $length; ++$i) {
            $currentDomain = implode('.', \array_slice($hostSegments, $i));
            $target = '.' . $currentDomain;
            if ($currentDomain === $hubDomain || str_ends_with($hubDomain, $target)) {
                return $target;
            }
        }

        throw new \RuntimeException(\sprintf('Unable to create authorization cookie for a hub on the different second-level domain "%s".', $hubDomain));
    }
}
