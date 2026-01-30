<?php

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Raneomik\NetteMercure\Core;

use Nette\Http\Request;
use Nette\Http\Response;
use Symfony\Component\Mercure\HubRegistry;
use Symfony\Component\WebLink\HttpHeaderSerializer;
use Symfony\Component\WebLink\Link;

final readonly class AddLinkHeaderHandler
{
	public function __construct(
	    private HubRegistry $registry,
	    private HttpHeaderSerializer $headerSerializer,
	    private Request $request,
	    private Response $response,
	    private string $hub,
	) {}

	public function __invoke(): void
	{
		$this->addLink($this->hub);
	}

	/**
	 * Add mercure link header to the given request.
	 */
	private function addLink(?string $hub = null): void
	{
		if ($this->isPreflightRequest($this->request)) {
			return;
		}

		$hubInstance = $this->registry->getHub($hub);

		/** @phpstan-ignore argument.type */
		$this->response->setHeader('Link', $this->headerSerializer->serialize([
		    new Link('mercure', $hubInstance->getPublicUrl()),
		]));
	}

	private function isPreflightRequest(Request $request): bool
	{
		return $request->isMethod('OPTIONS')
			&& null !== $request->getHeader('Access-Control-Request-Method');
	}
}
