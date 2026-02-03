<?php

/*
 * This file is part of the Mercure Component project.
 *
 * (c) Kévin Dunglas <dunglas@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Raneomik\NetteMercure\Core\Response;

use Nette\Http\Request;
use Nette\Http\Response;
use Symfony\Component\WebLink\HttpHeaderSerializer;
use Symfony\Component\WebLink\Link;

/**
 * Discovery service is a helper to add `Link` header to the response.
 */
final readonly class Discovery
{
    public function __construct(
        private HttpHeaderSerializer $header,
        private Request $request,
        private Response $response,
    ) {}

    /**
     * Add mercure link header to the given request.
     */
    public function addLink(string $hubLink): void
    {
        if ($this->isPreflightRequest($this->request)) {
            return;
        }

        // @phpstan-ignore-next-line
        $this->response->setHeader('Link', $this->header->serialize([
            new Link('mercure', $hubLink),
        ]));
        $this->response->setHeader('Access-Control-Allow-Origin', '*'); // ou ton domaine spécifique
        $this->response->setHeader('Access-Control-Allow-Credentials', 'true');
        $this->response->setHeader('Access-Control-Allow-Headers', 'Authorization, Content-Type');

    }

    private function isPreflightRequest(Request $request): bool
    {
        return $request->isMethod('OPTIONS')
            && null !== $request->getHeader('Access-Control-Request-Method');
    }
}
