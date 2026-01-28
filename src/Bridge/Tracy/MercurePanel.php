<?php

declare(strict_types=1);

namespace Nette\Mercure\Bridge\Tracy;

use Nette\Mercure\Bridge\Utils\BroadcastersLoader;
use Nette\Mercure\Core\Broadcasters;
use Nette\Mercure\Bridge\Tracy\Model\HubData;
use Nette\Utils\Helpers;
use Tracy;

final readonly class MercurePanel implements Tracy\IBarPanel
{
	private Broadcasters $broadcasters;

	private string $icon;

	public function __construct(
		private BroadcastersLoader $broadcastersLoader,
		private string $hotReloadUrl = '',
	) {
		$this->icon = file_get_contents(__DIR__ . '/dist/mercure.svg') ?: '';
		Tracy\Debugger::$customJsFiles[] = $this->hotReloadScript();
	}

	public function broadcasters(): Broadcasters
	{
		return $this->broadcasters ??= ($this->broadcastersLoader)();
	}

	private function hotReloadScript(): string
	{
		return Helpers::capture(function (): void {
			$hotReloadUrl = $this->hotReloadUrl;

			require_once __DIR__ . '/dist/hotReload.js.phtml';
		});
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
}
