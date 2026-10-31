<?php

declare(strict_types=1);

namespace Tests\Fixtures\Dummies\Core\Publish;

use Raneomik\NetteMercure\BroadcasterInterface;
use Symfony\Component\Mercure\HubInterface;

final readonly class DummyBroadcaster implements BroadcasterInterface
{
    private \Closure $publisher;

    public function __construct(
        private HubInterface $hub,
        callable $publisher,
    ) {
        /* @phpstan-ignore-next-line assign.propertyType */
        $this->publisher = $publisher;
    }

    public function broadcasterHub(): HubInterface
    {
        return $this->hub;
    }

    public function broadcasterUrl(): string
    {
        return $this->hub->getPublicUrl();
    }

    public function broadcastOptions(): array
    {
        return [$this->hub->getCookieName(), $this->hub->getProtocolVersion()];
    }

    public function broadcast(array|string $topics, object|array|string $data, array $options = [], ?string $template = null): string
    {
        return ($this->publisher)(
            $topics,
            $data,
            $options,
            $template,
        );
    }
}
