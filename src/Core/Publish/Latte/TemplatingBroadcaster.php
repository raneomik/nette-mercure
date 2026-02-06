<?php

declare(strict_types=1);

namespace Raneomik\NetteMercure\Core\Publish\Latte;

use Latte\Engine;
use Raneomik\NetteMercure\BroadcasterInterface;
use Raneomik\NetteMercure\Core\Publish\Latte\TurboStream\Action;
use Raneomik\NetteMercure\Core\Publish\PlainBroadcaster;

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

        $options['rendered_data'] = $renderedData = $this->latte->renderToString(
            $options['template'] = $this->templatePathResolver->resolve($template),
            $data + [
                'contentType' => $this->resolveContentType($template),
                'target' => $options['target'] ?? null,
            ],
            $this->resolveAction($options['action'] ?? null),
        );

        return $this->decorated->broadcast(
            $topics,
            $renderedData,
            $options,
        );
    }

    private function resolveContentType(string $template): string
    {
        if (
            str_ends_with($template, 'Stream.latte')
            || str_ends_with($template, '.stream.latte')
        ) {
            return 'text/vnd.turbo-stream.html';
        }

        if (str_ends_with($template, '.json.latte')) {
            return 'application/json';
        }

        return 'text/html';
    }

    private function resolveAction(Action|string|null $action): ?string
    {
        if ($action instanceof Action) {
            $action = $action->value;
        }

        if (\is_string($action)) {
            $action = Action::from($action)->value;
        }

        return $action ?? null;
    }
}
