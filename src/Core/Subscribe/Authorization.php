<?php

declare(strict_types=1);

namespace Raneomik\NetteMercure\Core\Subscribe;

use Nette\Http\FetchSite;
use Nette\Http\IRequest;
use Nette\Http\IResponse;
use Nette\Http\Response;
use Nette\Http\SameSite;
use Raneomik\NetteMercure\Bridge\DI\Config\ConfigurationRegistry;
use Symfony\Component\Mercure\ProtocolVersion;

final readonly class Authorization implements AuthorizationInterface
{
    public function __construct(
        private JWTProviderInterface $jwtProvider,
        private IRequest $request,
        private IResponse $response,
        private ConfigurationRegistry $config,
        private ?SameSite $cookieSameSite = SameSite::Strict,
    ) {
    }

    #[\Override]
    public function setCookie(array|string $grants = [], array $additionalClaims = [], ?string $hub = null): void
    {
        $jwt = $this->jwtProvider->provide($hub, $grants, $additionalClaims);
        $expire = $jwt->ttl ?? 0; // 0: session cookie
        [$name, $path, $domain, $secure] = $this->cookieParameters($hub);

        // IResponse::setCookie() has no "sameSite" parameter, only Nette\Http\Response does
        if (
            $this->response instanceof Response
            && $this->cookieSameSite instanceof SameSite
        ) {
            $this->response->setCookie($name, $jwt->token, $expire, $path, $domain, $secure, true, $this->cookieSameSite->value);

            return;
        }

        $this->response->setCookie($name, $jwt->token, $expire, $path, $domain, $secure, true);
    }

    #[\Override]
    public function clearCookie(?string $hub = null): void
    {
        [$name, $path, $domain, $secure] = $this->cookieParameters($hub);

        $this->response->deleteCookie($name, $path, $domain, $secure);
    }

    /**
     * @return array{string, string, ?string, bool} name, path, domain, secure
     */
    private function cookieParameters(?string $hub): array
    {
        $hubConfig = $this->config->getConfiguration($hub);

        /** @var array{
         * scheme?: string,
         * host?: string,
         * port?: int,
         * path?: string,
         * } $urlComponents */
        $urlComponents = parse_url($hubConfig->publicUrl);
        $secure = 'http' !== strtolower($urlComponents['scheme'] ?? 'https');

        return [
            $this->getCookieName($hubConfig->protocolVersion, $secure),
            $urlComponents['path'] ?? '/',
            $this->getCookieDomain($urlComponents['host'] ?? null),
            $secure,
        ];
    }

    private function getCookieName(ProtocolVersion $protocolVersion, bool $secure): string
    {
        $cookieName = $protocolVersion->getDefaultCookieName();
        if (! $secure && (str_starts_with($cookieName, '__Secure-') || str_starts_with($cookieName, '__Host-'))) {
            throw new \InvalidArgumentException(\sprintf('The "%s" cookie name requires a hub public URL served over HTTPS.', $cookieName));
        }

        return $cookieName;
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
