<?php

declare(strict_types=1);

namespace Raneomik\NetteMercure\Bridge\Tracy;

use Tracy;

/**
 * @phpstan-import-type LightHub from MercurePanel
 */
final readonly class CustomScriptsLoader
{
    public function __construct(
        ?string $hotReloadUrl = null,
        ?string $hotReloadOutputPath = null,
    ) {
        if (null !== $hotReloadUrl) {
            $this->registerHotReload($hotReloadUrl, $hotReloadOutputPath ?? __DIR__ . '/dist/hotReload.js');
        }
    }

    private function registerHotReload(string $url, string $outputPath): void
    {
        $js = Tracy\Helpers::capture(static function () use ($url): void {
            $hotReloadUrl = $url;
            require __DIR__ . '/dist/hotReload.js.phtml';
        });
        $this->register($js, $outputPath);
    }

    /**
     * @param non-empty-array<LightHub> $liveHubs
     */
    public function registerLiveEvents(array $liveHubs): void
    {
        $js = Tracy\Helpers::capture(static function () use ($liveHubs): void {
            require __DIR__ . '/dist/liveEvents.js.phtml';
        });
        // shortcut: liveEvents.js overwritten each request, race on concurrent devs
        $this->register($js, __DIR__ . '/dist/liveEvents.js');
    }

    private function register(string $js, string $path): void
    {
        if (@file_get_contents($path) !== $js) {
            file_put_contents($path, $js);
        }

        Tracy\Debugger::$customJsFiles[] = $path;
    }
}
