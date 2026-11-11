<?php

declare(strict_types=1);

namespace Raneomik\NetteMercure\Bridge\Tracy;

use Raneomik\NetteMercure\Bridge\DI\Config\ConfigurationRegistry;
use Raneomik\NetteMercure\Bridge\Tracy\Value\HubData;
use Raneomik\NetteMercure\Core\Publish\Broadcasters;
use Symfony\Component\Mercure\ProtocolVersion;
use Tracy;

/**
 * @phpstan-type LightHub array{name: string, url: string, isLegacy: bool}
 */
final class MercurePanel implements Tracy\IBarPanel
{
    private Broadcasters $broadcasters;

    /**
     * @var LightHub[]
     */
    private array $liveHubs;

    private readonly string $icon;

    private readonly string $css;

    /**
     * What the tab drained, for the panel rendered right after it
     */
    private ?HubData $hubData = null;

    public function __construct(
        private readonly \Closure $broadcastersLoader,
        private readonly ConfigurationRegistry $hubsConfig,
        private readonly CustomScriptsLoader $customScriptsLoader,
    ) {
        $this->icon = file_get_contents(__DIR__ . '/dist/mercure.svg') ?: '';
        $this->css = file_get_contents(__DIR__ . '/dist/panel.css') ?: '';
    }

    public function getTab(): string
    {
        return Tracy\Helpers::capture(function (): void {
            $icon = $this->icon;
            $hubData = new HubData($this->broadcasters());
            $this->hubData = $hubData;
            // an AJAX or redirect bar joins the bar of a page which already runs them
            $scripts = $this->isLive()
                ? $this->customScriptsLoader->scripts($this->liveEventsHubs(), $icon, $this->css)
                : [];
            $nonce = Tracy\Helpers::getNonce(true);

            require __DIR__ . '/dist/tab.phtml';
        });
    }

    public function getPanel(): string
    {
        return Tracy\Helpers::capture(function (): void {
            $hubData = $this->hubData ?? new HubData($this->broadcasters());
            $this->hubData = null;
            $hubsConfig = $this->hubsConfig;
            $icon = $this->icon;
            $isLive = $this->isLive();
            $css = $this->css;
            $transports = [];
            $cookies = [];

            foreach ($hubsConfig as $hubName => $config) {
                $transports[$hubName] = (new \ReflectionClass($this->broadcasters()[$hubName]->broadcasterHub()))->getShortName();
                $cookieName = $config->protocolVersion->getDefaultCookieName();
                $cookies[$hubName] = [
                    'name' => $cookieName,
                    'payload' => self::cookiePayload($cookieName, headers_list()),
                ];
            }

            $nonce = Tracy\Helpers::getNonce(true);

            require __DIR__ . '/dist/panel.phtml';
        });
    }

    /**
     * The claims of the authorization cookie set by this response, as JSON (not verified: display only).
     * The cookie path is the hub's one: the browser never sends it back to the application, a response is all we see.
     *
     * @internal
     * @param string[] $headers
     */
    public static function cookiePayload(string $cookieName, array $headers): ?string
    {
        foreach (array_reverse($headers) as $header) { // the last one wins
            if (preg_match('#^Set-Cookie:\s*' . preg_quote($cookieName, '#') . '=[^.;]+\.([\w-]+)\.#i', $header, $match)) {
                return base64_decode(strtr($match[1], '-_', '+/'), true) ?: null;
            }
        }

        return null;
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

    /**
     * Only the bar of a page listens to live events: an AJAX or redirect bar is a row added to it.
     */
    private function isLive(): bool
    {
        return ! isset($_SERVER['HTTP_X_REQUESTED_WITH'])
            && ! isset($_SERVER['HTTP_X_TRACY_AJAX'])
            && ! preg_match('#^Location:#im', implode("\n", headers_list()));
    }
}
