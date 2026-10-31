<?php

declare(strict_types=1);

namespace Raneomik\NetteMercure\Core\Publish\Tracy;

use Nette\Utils\Json;
use Raneomik\NetteMercure\BroadcasterInterface;
use Raneomik\NetteMercure\Core\Publish\Tracy\Value\BroadcastData;
use Raneomik\NetteMercure\Core\Publish\Tracy\Value\BroadcastStatus;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;

final class TraceableBroadcaster implements BroadcasterInterface
{
    /**
     * Public topic on which every publication is announced, with its metadata, for the Tracy panel.
     * An SSE event only carries its type, id and data: topics, visibility or hub never reach subscribers.
     */
    public const string DEBUG_TOPIC = '_debug/mercure';

    public const string DEBUG_EVENT = 'mercure-debug';

    /**
     * @var BroadcastData[]
     */
    private array $broadcastData = [];

    public function __construct(
        private readonly BroadcasterInterface $broadcaster,
        private readonly Metrics $metrics = new Metrics(),
        private readonly ?string $hubName = null,
    ) {
    }

    #[\Override]
    public function broadcasterHub(): HubInterface
    {
        return $this->broadcaster->broadcasterHub();
    }

    public function broadcasterUrl(): string
    {
        return $this->broadcaster->broadcasterUrl();
    }

    public function broadcastOptions(): array
    {
        return $this->broadcaster->broadcastOptions();
    }

    #[\Override]
    public function broadcast(
        array|string $topics,
        array|object|string $data,
        array $options = [],
        ?string $template = null,
    ): string {
        // wall clock in ms: comparable with the browser's Date.now() (durations come from the metrics)
        $sentAt = microtime(true) * 1000;
        $error = null;
        $messageId = '';
        $status = BroadcastStatus::Sent;

        $this->metrics->start($key = self::class);
        try {
            $messageId = $this->broadcaster->broadcast($topics, $data, $options, $template);
        } catch (\Throwable $throwable) {
            $status = BroadcastStatus::Error;
            $error = $throwable;
        }

        $this->metrics->stop($key);

        $options = $this->broadcastOptions();

        $options['template'] ??= $template;

        $this->broadcastData[] = $broadcastData = new BroadcastData(
            $status,
            (array) ($options['topics'] ?? $topics),
            $data,
            $sentAt,
            \strlen($options['rendered_data'] ?? (
                \is_string($data) ? $data : Json::encode($data)
            )),
            $this->metrics->getDuration($key),
            $this->metrics->getMemory($key),
            $options,
            $error,
            '' !== $messageId ? $messageId : null,
            $this->hubName,
        );

        $this->publishDebug($broadcastData);

        return $messageId;
    }

    /**
     * @return BroadcastData[]
     */
    public function broadcastData(): array
    {
        return $this->broadcastData;
    }

    public function count(): int
    {
        return \count($this->broadcastData);
    }

    public function getDuration(): float
    {
        return (float) array_sum(array_column($this->broadcastData, 'duration'));
    }

    public function getMemory(): float
    {
        return array_sum(array_column($this->broadcastData, 'memory'));
    }

    public function getSize(): float
    {
        return array_sum(array_column($this->broadcastData, 'size'));
    }

    private function publishDebug(BroadcastData $broadcastData): void
    {
        try {
            $this->broadcasterHub()
                ->publish(new Update(
                    self::DEBUG_TOPIC,
                    json_encode($broadcastData, \JSON_THROW_ON_ERROR | \JSON_INVALID_UTF8_SUBSTITUTE | \JSON_UNESCAPED_SLASHES),
                    type: self::DEBUG_EVENT,
                ));
        } catch (\Throwable) {
            // debug tooling must never break a real publication (nor hide its own error with a second one)
        }
    }
}
