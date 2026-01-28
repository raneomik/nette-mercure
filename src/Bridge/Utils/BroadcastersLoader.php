<?php

declare(strict_types=1);

namespace Nette\Mercure\Bridge\Utils;

use Nette\Mercure\Core\Broadcasters;

final readonly class BroadcastersLoader
{
	private object $broadcastersLoader;

	public function __construct(
		callable $broadcastersLoader,
	) {
		// @phpstan-ignore-next-line
		$this->broadcastersLoader = $broadcastersLoader;
	}

	public function __invoke(): Broadcasters
	{
		return ($this->broadcastersLoader)();
	}
}
