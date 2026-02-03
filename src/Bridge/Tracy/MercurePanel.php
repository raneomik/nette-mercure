<?php

declare(strict_types=1);

namespace Raneomik\NetteMercure\Bridge\Tracy;

use Raneomik\NetteMercure\Bridge\Tracy\Model\HubData;
use Raneomik\NetteMercure\Bridge\Utils\BroadcastersLoader;
use Raneomik\NetteMercure\Core\Broadcasters;
use Tracy;
use Tracy\Helpers;

final readonly class MercurePanel implements Tracy\IBarPanel
{
    private Broadcasters $broadcasters;

    private string $icon;

    public function __construct(
        private BroadcastersLoader $broadcastersLoader,
        private ?string $hotReloadUrl = null,
    ) {
        $this->icon = file_get_contents(__DIR__ . '/dist/mercure.svg') ?: '';

        if (null !== $hotReloadUrl) {
            Tracy\Debugger::$customJsFiles[] = $this->hotReloadScript();
        }
    }

    public function broadcasters(): Broadcasters
    {
        return $this->broadcasters ??= ($this->broadcastersLoader)();
    }

    public function getTab(): string
    {
        return Helpers::capture(function (): void {
            $name = 'Mercure';
            $icon = $this->icon;
            $count = $this->broadcasters()->count();

            require_once __DIR__ . '/dist/tab.phtml';
        });
    }

    public function getPanel(): string
    {
        return Helpers::capture(function (): void {
            $hubData = new HubData($this->broadcasters());
            $icon = $this->icon;

            require __DIR__ . '/dist/panel.phtml';
        });
    }

    private function hotReloadScript(): string
    {
        $file = __DIR__ . '/dist/hotReload.js';

        file_put_contents($file, Helpers::capture(function (): void {
            $hotReloadUrl = $this->hotReloadUrl;

            require_once __DIR__ . '/dist/hotReload.js.phtml';
        }));

        return $file;
    }
}
