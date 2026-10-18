<?php

declare(strict_types=1);

/**
 * Stub of the function provided by the FrankenPHP built-in Mercure hub (Go extension, unavailable in CLI).
 * Only load it in the test processes simulating a FrankenPHP runtime.
 *
 * @param string|string[] $topics
 */
function mercure_publish(
    string|array $topics,
    string $data = '',
    bool $private = false,
    ?string $id = null,
    ?string $type = null,
    ?int $retry = null,
): string {
    return $id ?? 'urn:uuid:frankenphp-stub';
}
