<?php

declare(strict_types=1);

namespace Raneomik\NetteMercure\Core\Publish\Latte;

use Latte\Engine;
use Raneomik\NetteMercure\BroadcasterInterface;
use Raneomik\NetteMercure\Core\Publish\PlainBroadcaster;
use Symfony\Component\Mercure\HubInterface;

final readonly class TemplatingBroadcaster implements BroadcasterInterface
{
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
    public function broadcasterHub(): HubInterface
    {
        return $this->decorated->broadcasterHub();
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
                'contentType' => $contentType->value,
                'target' => $options['target'] ?? null,
            ],
            $action?->value,
        );

        if (ContentType::TurboStream === $contentType) {
            $options['sse_type'] = 'turbo-stream';
        }

        return $this->decorated->broadcast(
            $topics,
            $renderedData,
            $options,
        );
    }

    private function resolveContentType(string $template): ContentType
    {
        if (
            str_ends_with($template, 'Stream.latte')
            || str_ends_with($template, '.stream.latte')
        ) {
            return ContentType::TurboStream;
        }

        if (str_ends_with($template, '.json.latte')) {
            return ContentType::Json;
        }

        return ContentType::Html;
    }

    private function resolveAction(string|TurboStreamAction|null $action): ?TurboStreamAction
    {
        return match (true) {
            \is_string($action) => TurboStreamAction::from($action),
            $action instanceof TurboStreamAction => $action,
            default => null,
        };
    }
}
