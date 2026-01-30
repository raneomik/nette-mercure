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

namespace Raneomik\NetteMercure\Core;

use Nette\Http\Request;
use Nette\Http\Response;
use Symfony\Component\Mercure\HubRegistry;
use Symfony\Component\WebLink\HttpHeaderSerializer;
use Symfony\Component\WebLink\Link;

/**
 * Discovery service is a helper to add `Link` header to the response.
 */
final readonly class Discovery
{
	public function __construct(
	    private HubRegistry $registry,
	    private HttpHeaderSerializer $header,
	    private Request $request,
	    private Response $response,
	) {}

	/**
	 * Add mercure link header to the given request.
	 */
	public function addLink(?string $hub = null): void
	{
		if ($this->isPreflightRequest($this->request)) {
			return;
		}

		$hubInstance = $this->registry->getHub($hub);

		// @phpstan-ignore-next-line
		$this->response->setHeader('Link', $this->header->serialize([
		    new Link('mercure', $hubInstance->getPublicUrl()),
		]));
	}

	private function isPreflightRequest(Request $request): bool
	{
		return $request->isMethod('OPTIONS')
			&& null !== $request->getHeader('Access-Control-Request-Method');
	}
}
