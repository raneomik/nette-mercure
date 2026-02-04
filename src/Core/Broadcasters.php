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

use Raneomik\NetteMercure\BroadcasterInterface;
use Raneomik\NetteMercure\Exception\BroadcastException;

/**
 * @implements \IteratorAggregate<string, BroadcasterInterface>
 * @implements \ArrayAccess<string, BroadcasterInterface>
 */
final class Broadcasters implements BroadcasterInterface, \IteratorAggregate, \ArrayAccess
{
	/**
	 * @var \ArrayIterator<string, BroadcasterInterface>
	 */
	private readonly iterable $broadcasters;

	/**
	 * @param iterable<string, BroadcasterInterface> $broadcasters
	 */
	public function __construct(
	    iterable $broadcasters,
	    private false|string $currentHub = false,
	) {
		/** @phpstan-ignore-next-line */
		$this->broadcasters = new \ArrayIterator($broadcasters);
	}

	#[\Override]
	public function broadcasterUrl(?string $hub = null): string
	{
		if (null !== $hub) {
			return $this->getHub($hub)->broadcasterUrl();
		}

		return $this[$this->currentHub ?: '']->broadcasterUrl();
	}

	#[\Override]
	public function broadcastOptions(?string $hub = null): array
	{
		if (null !== $hub) {
			return $this->getHub($hub)->broadcastOptions();
		}

		return $this[$this->currentHub ?: '']->broadcastOptions();
	}

	#[\Override]
	public function broadcast(
	    array|string $topics,
	    object|array|string $data,
	    array $options = [],
	    ?string $template = null,
	    bool $toAll = false,
	): string {
		$hub = $options['hub'] ?? false;

		if ($hub) {
			return $this->getHub($hub)->broadcast($topics, $data, $options, $template);
		}

		if (false === $toAll) {
			return $this->first()->broadcast($topics, $data, $options, $template);
		}

		$publishId = [];
		foreach ($this->broadcasters as $broadcaster) {
			$publishId[] = $broadcaster->broadcast($topics, $data, $options, $template);
		}

		return implode(';', $publishId);
	}

	public function count(): int
	{
		return \count($this->broadcasters);
	}

	/**
	 * @return \ArrayIterator<string, BroadcasterInterface>
	 */
	public function getIterator(): \ArrayIterator
	{
		return $this->broadcasters;
	}

	public function first(): BroadcasterInterface
	{
		foreach ($this->broadcasters as $hub => $broadcaster) {
			$this->currentHub = $hub;
			return $broadcaster;
		}

		throw new BroadcastException('No broadcaster defined.');
	}

	public function offsetGet(mixed $offset): BroadcasterInterface
	{
		return $this->broadcasters[$offset] ?? $this->first();
	}

	public function offsetExists(mixed $offset): bool
	{
		return isset($this->broadcasters[$offset]);
	}

	public function offsetSet(mixed $offset, mixed $value): void
	{
		throw new \LogicException('Cannot modify readonly collection.');
	}

	public function offsetUnset(mixed $offset): void
	{
		throw new \LogicException('Cannot modify readonly collection.');
	}

    private function getHub(string $name): BroadcasterInterface
    {
        if (false === ($this[$name] ?? false)) {
            throw new BroadcastException(sprintf('The hub "%s" is not defined.', $name));
        }

        $this->currentHub = $name;
        return $this[$name];
    }
}
