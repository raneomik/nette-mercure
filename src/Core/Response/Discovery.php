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

use Nette\Http\IRequest;
use Nette\Http\IResponse;
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
    ) {}

    /**
     * Add mercure link header to the given request.
     */
    public function addLink(string $hubLink): void
    {
        // avoid nette errors - does not accept OPTIONS requests
        if ($this->isPreflightRequest()) {
            return;
        }

        // @phpstan-ignore-next-line
        $this->response->setHeader('Link', $this->header->serialize([
            new Link('mercure', $hubLink),
        ]));
    }

    public function isPreflightRequest(): bool
    {
        return $this->request->isMethod('OPTIONS')
            && null !== $this->request->getHeader('Access-Control-Request-Method');
    }
}
