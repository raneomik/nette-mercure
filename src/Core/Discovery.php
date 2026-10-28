<?php

declare(strict_types=1);

namespace Raneomik\NetteMercure\Core;

use Nette\Http\IRequest;
use Nette\Http\IResponse;
use Raneomik\NetteMercure\Bridge\DI\Config\ConfigurationRegistry;
use Symfony\Component\WebLink\HttpHeaderSerializer;
use Symfony\Component\WebLink\Link;

/**
 * Discovery service is a helper to add `Link` header to the response.
 */
final readonly class Discovery
{
    public const string NO_LINK_DISCOVER = 'noMercureLinkDiscover';

    public function __construct(
        private HttpHeaderSerializer $header,
        private IRequest $request,
        private IResponse $response,
        private ConfigurationRegistry $config,
    ) {
    }

    public function addLinkFromCurrentRequest(): void
    {
        $noDiscovery = ((bool) $this->request->getQuery(self::NO_LINK_DISCOVER))
            || ((bool) $this->request->getHeader(self::NO_LINK_DISCOVER));

        if ($noDiscovery) {
            return;
        }

        $hub = $this->request->getQuery('hub')
            ?? $this->request->getQuery('hubName')
        ;

        try {
            $hubData = $this->config->getConfiguration($hub);
        } catch (\InvalidArgumentException) {
            return;
        }

        if (false === $hubData->autoDiscovery) {
            return;
        }

        $this->addLink($hubData->publicUrl);
    }

    private function addLink(string $hubLink): void
    {
        // avoid nette errors - eg. does not accept OPTIONS requests
        if ($this->isPreflightRequest()) {
            return;
        }

        $this->response->setHeader('Access-Control-Expose-Headers', 'Link');

        // @phpstan-ignore-next-line
        $this->response->setHeader('Link', $this->header->serialize([
            new Link('mercure', $hubLink),
        ]));
    }

    private function isPreflightRequest(): bool
    {
        return $this->request->isMethod('OPTIONS')
            && null !== $this->request->getHeader('Access-Control-Request-Method');
    }
}
