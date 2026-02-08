<?php

declare(strict_types=1);

namespace Raneomik\NetteMercure\Core\Publish\Latte;

use Latte\Engine;
use Raneomik\NetteMercure\BroadcasterInterface;
use Raneomik\NetteMercure\Core\Publish\Latte\TurboStream\Action;
use Raneomik\NetteMercure\Core\Publish\PlainBroadcaster;

final readonly class TemplatingBroadcaster implements BroadcasterInterface
{
    private const string DEFAULT_CONTENT_TYPE = 'text/html';

    private const string JSON_CONTENT_TYPE = 'application/json';

    private const string TURBO_STREAM_CONTENT_TYPE = 'text/vnd.turbo-stream.html';

    /**
     * @param PlainBroadcaster $decorated
     */
    public function __construct(
        private BroadcasterInterface $decorated,
        private TemplatePathResolver $templatePathResolver,
        private Engine $latte,
    ) {
    }

    #[\Override]
    public function broadcasterUrl(): string
    {
        return $this->decorated->broadcasterUrl();
    }

    #[\Override]
    public function broadcastOptions(): array
    {
        return $this->decorated->broadcastOptions();
    }

    #[\Override]
    public function broadcast(
        array|string $topics,
        array|object|string $data,
        array $options = [],
        ?string $template = null,
    ): string {
        $template = $options['template'] ?? $template;

        if (null === $template) {
            return $this->decorated->broadcast($topics, $data, $options);
        }

        $data = \is_string($data)
            ? [
                'data' => $data,
            ]
            : (array) $data;

        $contentType = $this->resolveContentType($template);
        $action = $this->resolveAction($options['action'] ?? null);
        $options['rendered_data'] = $renderedData = $this->latte->renderToString(
            $options['template'] = $this->templatePathResolver->resolve($template),
            $data + [
                'contentType' => $contentType,
                'target' => $options['target'] ?? null,
            ],
            $action,
        );

        if ($this->isTurbo($contentType)) {
            $options['sse_type'] = 'turbo-stream';
        }

        return $this->decorated->broadcast(
            $topics,
            $renderedData,
            $options,
        );
    }

    private function isTurbo(string $contentType): bool
    {
        return self::TURBO_STREAM_CONTENT_TYPE === $contentType;
    }

    private function resolveContentType(string $template): string
    {
        if (
            str_ends_with($template, 'Stream.latte')
            || str_ends_with($template, '.stream.latte')
        ) {
            return self::TURBO_STREAM_CONTENT_TYPE;
        }

        if (str_ends_with($template, '.json.latte')) {
            return self::JSON_CONTENT_TYPE;
        }

        return self::DEFAULT_CONTENT_TYPE;
    }

    private function resolveAction(Action|string|null $action): ?string
    {
        return match (true) {
            \is_string($action) => Action::from($action)->value,
            $action instanceof Action => $action->value,
            default => null,
        };
    }
}
