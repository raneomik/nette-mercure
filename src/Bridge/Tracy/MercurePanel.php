<?php

declare(strict_types=1);

namespace Raneomik\NetteMercure\Bridge\Tracy;

use Raneomik\NetteMercure\Bridge\DI\Config\ConfigurationRegistry;
use Raneomik\NetteMercure\Bridge\Tracy\Value\HubData;
use Raneomik\NetteMercure\Bridge\Utils\BroadcastersLoader;
use Raneomik\NetteMercure\Core\Publish\Broadcasters;
use Symfony\Component\Mercure\ProtocolVersion;
use Tracy;

/**
 * @phpstan-type LightHub array{name: string, url: string, isLegacy: bool}
 */
final readonly class MercurePanel implements Tracy\IBarPanel
{
    private Broadcasters $broadcasters;

    /**
     * @var LightHub[]
     */
    private array $liveHubs;

    private string $icon;

    public function __construct(
        private BroadcastersLoader $broadcastersLoader,
        private ConfigurationRegistry $hubsConfig,
        private CustomScriptsLoader $customScriptsLoader,
    ) {
        $this->icon = file_get_contents(__DIR__ . '/dist/mercure.svg') ?: '';
    }

    public function setupLiveEvents(): void
    {
        $liveHubs = $this->liveEventsHubs();
        if ([] === $liveHubs) {
            return;
        }

        $this->customScriptsLoader->registerLiveEvents($liveHubs);
    }

    public function getTab(): string
    {
        return Tracy\Helpers::capture(function (): void {
            $name = 'Mercure';
            $icon = $this->icon;
            $count = $this->broadcasters()->count();

            require __DIR__ . '/dist/tab.phtml';
        });
    }

    public function getPanel(): string
    {
        return Tracy\Helpers::capture(function (): void {
            $hubData = new HubData($this->broadcasters());
            $icon = $this->icon;
            $liveHubs = $this->liveEventsHubs();

            require __DIR__ . '/dist/panel.phtml';
        });
    }

    private function broadcasters(): Broadcasters
    {
        return $this->broadcasters ??= ($this->broadcastersLoader)();
    }

    /**
     * @return LightHub[]
     */
    private function liveEventsHubs(): array
    {
        if (isset($this->liveHubs)) {
            return $this->liveHubs;
        }

        $hubs = [];
        foreach ($this->hubsConfig as $hubName => $config) {
            $hubs[] = [
                'name' => $hubName,
                'url' => $config->hubUrl,
                'isLegacy' => ProtocolVersion::Legacy === $config->protocolVersion,
            ];
        }

        return $this->liveHubs = $hubs;
    }
}
