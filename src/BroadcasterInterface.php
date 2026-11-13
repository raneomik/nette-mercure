<?php

declare(strict_types=1);

namespace Raneomik\NetteMercure;

use Raneomik\NetteMercure\Core\Publish\Latte\TurboStreamAction;
use Symfony\Component\Mercure\HubInterface;

interface BroadcasterInterface
{
    public function broadcasterHub(
        // ?string $hub = null
    ): HubInterface;

    public function broadcasterUrl(
        // ?string $hub = null
    ): string;

    /**
     * @return array{
     *     topics?: string|string[],
     *     private?: bool,
     *     hub?: string,
     *     template?: string,
     *     rendered_data?: string,
     *     action?: string|TurboStreamAction,
     *     sse_id?: string,
     *     sse_type?: string,
     *     sse_retry?: int,
     *     locale?: string,
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
     *     template?: string,
     *     rendered_data?: string,
     *     action?: string|TurboStreamAction,
     *     target?: string,
     *     sse_id?: string,
     *     sse_type?: string,
     *     sse_retry?: int,
     *     locale?: string,
     *     locales?: string[],
     * } $options "locale" / "locales": on a localized hub, the locale(s) to render (default: every locale)
     */
    public function broadcast(
        array|string $topics,
        array|object|string $data,
        array $options = [],
        ?string $template = null,
        // bool $toAll = false,
    ): string;
}
