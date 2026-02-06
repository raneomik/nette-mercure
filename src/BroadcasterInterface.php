<?php

declare(strict_types=1);

namespace Raneomik\NetteMercure;

use Raneomik\NetteMercure\Core\Publish\Latte\TurboStream\Action;

interface BroadcasterInterface
{
    public function broadcasterUrl(
        // ?string $hub = null
    ): string;

    /**
     * @return array{
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
     * }
     */
    public function broadcastOptions(
        // ?string $hub = null
    ): array;

    /**
     * @param string|string[] $topics
     * @param array<string, mixed>|object|string $data
     * @param array{
     *     topics?: string|string[],
     *     private?: bool,
     *     hub?: string,
     *     contentType?: string,
     *     template?: string,
     *     rendered_data?: string,
     *     action?: Action|string,
     *     target?: string,
     *     sse_id?: string,
     *     sse_type?: string,
     *     sse_retry?: int,
     * } $options
     */
    public function broadcast(
        array|string $topics,
        array|object|string $data,
        array $options = [],
        ?string $template = null,
        // bool $toAll = false,
    ): string;
}
