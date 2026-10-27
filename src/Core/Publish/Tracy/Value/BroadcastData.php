<?php

declare(strict_types=1);

namespace Raneomik\NetteMercure\Core\Publish\Tracy\Value;

use Nette\Utils\Json;
use Nette\Utils\Strings;
use Raneomik\NetteMercure\Core\Publish\Latte\TurboStreamAction;

final readonly class BroadcastData implements \JsonSerializable
{
    private const int PREVIEW_LENGTH = 400;

    /**
     * Undocumented function.
     *
     * @param string[] $topics
     * @param array<string, mixed>|object|string $data
     * @param array<string,mixed> $options
     */
    public function __construct(
        public BroadcastStatus $status,
        public array $topics,
        public array|object|string $data,
        public float $sentAt = 0,
        public float $size = 0,
        public float $duration = 0,
        public float $memory = 0,
        private array $options = [],
        public ?\Throwable $error = null,
        public ?string $id = null,
        public ?string $hub = null,
    ) {
    }

    public function action(): string|TurboStreamAction|null
    {
        return $this->options['action'] ?? null;
    }

    public function template(): ?string
    {
        return $this->options['template'] ?? null;
    }

    public function renderedData(): ?string
    {
        return $this->options['rendered_data'] ?? null;
    }

    public function errorMessage(): ?string
    {
        return $this->error?->getMessage() ?? null;
    }

    /**
     * @return array<string,mixed>
     */
    public function options(): array
    {
        return array_diff_key($this->options, array_flip([
            'action',
            'template',
            'topics',
            'rendered_data',
        ]));
    }

    /**
     * Payload announced on the debug topic: lean (no copy of the whole message, only a preview) and safe to
     * encode (a Throwable would serialize to an empty object).
     *
     * @return array<string,mixed>
     */
    public function jsonSerialize(): array
    {
        $rendered = $this->renderedData()
            ?? (\is_string($this->data) ? $this->data : Json::encode($this->data));
        $action = $this->action();
        $action = $action instanceof TurboStreamAction ? $action->value : $action;

        $template = $this->template();

        return [
            'id' => $this->id,
            'hub' => $this->hub,
            'status' => $this->status,
            'topics' => $this->topics,
            'private' => (bool) ($this->options['private'] ?? false),
            'type' => $this->options['sse_type'] ?? null,
            'action' => $action,
            'target' => $this->options['target'] ?? null,
            'template' => $template,
            'sentAt' => $this->sentAt,
            'size' => $this->size,
            'duration' => $this->duration,
            'memory' => $this->memory,
            'preview' => Strings::truncate($rendered, self::PREVIEW_LENGTH),
            'error' => $this->error instanceof \Throwable ? [
                'class' => $this->error::class,
                'message' => $this->error->getMessage(),
            ] : null,
        ];
    }
}
