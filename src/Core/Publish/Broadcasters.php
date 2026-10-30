<?php

declare(strict_types=1);

namespace Raneomik\NetteMercure\Core\Publish;

use Raneomik\NetteMercure\BroadcasterInterface;
use Raneomik\NetteMercure\Exception\BroadcastException;
use Symfony\Component\Mercure\HubInterface;

/**
 * @implements \IteratorAggregate<string, BroadcasterInterface>
 * @implements \ArrayAccess<string, BroadcasterInterface>
 */
final readonly class Broadcasters implements BroadcasterInterface, \ArrayAccess, \IteratorAggregate
{
    /**
     * @param array<string, BroadcasterInterface> $broadcasters
     */
    public function __construct(
        private iterable $broadcasters,
    ) {
    }

    public function broadcasterHub(?string $hub = null): HubInterface
    {
        return $this->pick($hub)
            ->broadcasterHub();
    }

    #[\Override]
    public function broadcasterUrl(?string $hub = null): string
    {
        return $this->pick($hub)
            ->broadcasterUrl();
    }

    #[\Override]
    public function broadcastOptions(?string $hub = null): array
    {
        return $this->pick($hub)
            ->broadcastOptions();
    }

    #[\Override]
    public function broadcast(
        array|string $topics,
        array|object|string $data,
        array $options = [],
        ?string $template = null,
        bool $toAll = false,
    ): string {
        $hub = $options['hub'] ?? false;

        if ($hub) {
            return $this->getHub($hub)
                ->broadcast($topics, $data, $options, $template)
            ;
        }

        if (false === $toAll) {
            return $this->first()
                ->broadcast($topics, $data, $options, $template)
            ;
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

    public function first(): BroadcasterInterface
    {
        foreach ($this->broadcasters as $broadcaster) {
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

    /**
     * Stateless on purpose: the service is shared by every request of a worker.
     */
    private function pick(?string $hub): BroadcasterInterface
    {
        return null === $hub ? $this->first() : $this->getHub($hub);
    }

    private function getHub(string $name): BroadcasterInterface
    {
        if (false === $this->offsetExists($name)) {
            throw new BroadcastException(\sprintf('The hub "%s" is not defined.', $name));
        }

        return $this[$name];
    }

    public function getIterator(): \Traversable
    {
        yield from $this->broadcasters;
    }
}
