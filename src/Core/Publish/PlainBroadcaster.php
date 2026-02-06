<?php

declare(strict_types=1);

namespace Raneomik\NetteMercure\Core\Publish;

use Nette\Utils\Json;
use Raneomik\NetteMercure\BroadcasterInterface;
use Raneomik\NetteMercure\Core\Publish\Latte\TurboStream\Action;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;

final class PlainBroadcaster implements BroadcasterInterface
{
    /**
     * @var array{
     *     topics?: string|string[],
     *     private?: bool,
     *     hub?: string,
     *     contentType?: string,
     *     template?: string,
     *     rendered_data?: string,
     *     action?: Action|string,
     *     sse_id?: string,
     *     sse_type?: string,
     *     sse_retry?: int,
     * } $resolvedOptions
     */
    private array $resolvedOptions;

    public function __construct(
        private readonly HubInterface $hub,
    ) {
    }

    #[\Override]
    public function broadcasterUrl(): string
    {
        return $this->hub->getPublicUrl();
    }

    #[\Override]
    public function broadcastOptions(): array
    {
        return $this->resolvedOptions;
    }

    #[\Override]
    public function broadcast(
        array|string $topics,
        array|object|string $data,
        array $options = [],
        ?string $template = null,
    ): string {
        $data = \is_string($data)
            ? $data
            : Json::encode($data);

        $update = new Update(
            $topics,
            $data,
            (bool) ($options['private'] ?? false),
            $options['sse_id'] ?? null,
            $options['sse_type'] ?? null,
            $options['sse_retry'] ?? null,
        );

        $this->resolvedOptions = $options + [
            'rendered_data' => $options['rendered_data'] ?? $data,
        ];

        return $this->hub->publish($update);
    }
}
