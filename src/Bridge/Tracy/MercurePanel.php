<?php

declare(strict_types=1);

namespace Raneomik\NetteMercure\Bridge\Tracy;

use Raneomik\NetteMercure\Bridge\Tracy\Value\HubData;
use Raneomik\NetteMercure\Bridge\Utils\BroadcastersLoader;
use Raneomik\NetteMercure\Core\Publish\Broadcasters;
use Tracy;

final readonly class MercurePanel implements Tracy\IBarPanel
{
    private Broadcasters $broadcasters;

    private string $icon;

    private string $jsFilename;

    public function __construct(
        private BroadcastersLoader $broadcastersLoader,
        private ?string $hotReloadUrl = null,
        ?string $jsFilename = null,
    ) {
        $this->icon = file_get_contents(__DIR__.'/dist/mercure.svg') ?: '';

        if (null !== $hotReloadUrl) {
            $this->jsFilename = $jsFilename ?? __DIR__.'/dist/hotReload.js';
            Tracy\Debugger::$customJsFiles[] = $this->hotReloadScript();
        }
    }

    public function getTab(): string
    {
        return Tracy\Helpers::capture(function (): void {
            $name = 'Mercure';
            $icon = $this->icon;
            $count = $this->broadcasters()
                ->count()
            ;

            require_once __DIR__.'/dist/tab.phtml';
        });
    }

    public function getPanel(): string
    {
        return Tracy\Helpers::capture(function (): void {
            $hubData = new HubData($this->broadcasters());
            $icon = $this->icon;

            require __DIR__.'/dist/panel.phtml';
        });
    }

    private function broadcasters(): Broadcasters
    {
        return $this->broadcasters ??= ($this->broadcastersLoader)();
    }

    private function hotReloadScript(): string
    {
        if (file_exists($this->jsFilename)) {
            return $this->jsFilename;
        }

        file_put_contents($this->jsFilename, Tracy\Helpers::capture(function (): void {
            $hotReloadUrl = $this->hotReloadUrl;

            require_once __DIR__.'/dist/hotReload.js.phtml';
        }));

        return $this->jsFilename;
    }
}
