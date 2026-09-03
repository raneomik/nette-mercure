<?php

declare(strict_types=1);

namespace Raneomik\NetteMercure\Core\Publish\Tracy;

use Raneomik\NetteMercure\BroadcasterInterface;
use Raneomik\NetteMercure\Core\Publish\Tracy\Value\BroadcastData;
use Symfony\Component\Mercure\HubInterface;

final class TraceableBroadcaster implements BroadcasterInterface
{
    /**
     * @var array<int, array{
     *        data: BroadcastData,
     *        duration: float,
     *        memory: float,
     *     }
     * >
     */
    private array $messages = [];

    public function __construct(
        private readonly BroadcasterInterface $broadcaster,
        private readonly Metrics $metrics = new Metrics(),
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
        $this->metrics->start(self::class);
        $messageId = $this->broadcaster->broadcast($topics, $data, $options, $template);
        $this->metrics->stop(self::class);

        $options = $this->broadcastOptions();

        $options['template'] ??= $template ?? 'n/a';
        $options['action'] ??= 'n/a';

        $this->messages[] = [
            'data' => new BroadcastData(
                (array) ($options['topics'] ?? $topics),
                $options['rendered_data'] ?? '',
                $options,
            ),
            'duration' => $this->metrics->getDuration(self::class),
            'memory' => $this->metrics->getMemory(self::class),
        ];

        return $messageId;
    }

    public function count(): int
    {
        return \count($this->messages);
    }

    /**
     * @return BroadcastData[]
     */
    public function getMessageData(): array
    {
        return array_column($this->messages, 'data');
    }

    public function getDuration(): float
    {
        return (float) array_sum(array_column($this->messages, 'duration'));
    }

    public function getMemory(): float
    {
        return array_sum(array_column($this->messages, 'memory'));
    }
}
