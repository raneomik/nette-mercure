<?php

declare(strict_types=1);

namespace Raneomik\NetteMercure\Core;

use Nette\Http\IRequest;
use Nette\Http\IResponse;
use Raneomik\NetteMercure\Bridge\Utils\ConfiguredDataRegistry;
use Symfony\Component\WebLink\HttpHeaderSerializer;
use Symfony\Component\WebLink\Link;

/**
 * Discovery service is a helper to add `Link` header to the response.
 */
final readonly class Discovery
{
    public function __construct(
        private HttpHeaderSerializer $header,
        private IRequest $request,
        private IResponse $response,
        private ConfiguredDataRegistry $config,
    ) {
    }

    public function addLinkFromCurrentRequest(): void
    {
        $hub = $this->request->getQuery('hub') ?? $this->request->getQuery('hubName');

        $hubData = $this->config->getConfiguration($hub);

        if (false === $hubData->autoDiscovery) {
            return;
        }

        $this->addLink($hubData->hubUrl);
    }

    public function addLink(string $hubLink): void
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
