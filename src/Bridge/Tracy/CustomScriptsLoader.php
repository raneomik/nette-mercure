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
        private ?string $hotReloadUrl = null,
    ) {
    }

    /**
     * @param LightHub[] $liveHubs
     * @return string[]
     */
    public function scripts(
        array $liveHubs,
        string $icon,
        bool $isAjax = false,
    ): array {
        $scripts = [];
        if (null !== $this->hotReloadUrl) {
            $scripts[] = Tracy\Helpers::capture(function (): void {
                $hotReloadUrl = $this->hotReloadUrl;

                require __DIR__ . '/dist/hotReload.js.phtml';
            });
        }

        if ([] !== $liveHubs && false === $isAjax) {
            $scripts[] = Tracy\Helpers::capture(static function (): void {
                require __DIR__ . '/dist/debugHook.js.phtml';
            });

            $scripts[] = Tracy\Helpers::capture(static function () use ($liveHubs, $icon): void {
                require __DIR__ . '/dist/liveEvents.js.phtml';
            });
        }

        return $scripts;
    }
}
