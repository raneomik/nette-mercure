<?php

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Nette\Mercure\Bridge\DI;

use Nette;
use Nette\DI\Definitions\ServiceDefinition;
use Nette\DI\Definitions\Statement;
use Nette\Mercure\Bridge\Utils\BroadcastersLoader;
use Nette\Mercure\Bridge\DI\Dependency\BroadcastersDefiner;
use Nette\Mercure\Bridge\DI\Dependency\MercureHubsDefiner;
use Nette\Mercure\Bridge\Latte\MercureExtension as LatteMercureExtension;
use Nette\Mercure\Bridge\Tracy\MercurePanel;
use Nette\Schema\Expect;
use Symfony\Component\Mercure\Jwt\LcobucciFactory;

final class MercureExtension extends Nette\DI\CompilerExtension
{
	public function __construct(
		private readonly bool $debugMode = false,
	) {}

	public function getConfigSchema(): Nette\Schema\Schema
	{
		return Expect::arrayOf(
			Expect::structure([
				'url' => Expect::string()->default('%baseUrl%/.well-known/mercure')->required()->dynamic(),
				'jwt' => Expect::structure([
					'secret' => Expect::string(getenv('MERCURE_JWT_SECRET_KEY') ?: '!m3rcur3C00ki3!')->dynamic(),
					'publish' => Expect::arrayOf('string')->default(['*'])->dynamic(),
					'subscribe' => Expect::arrayOf('string')->default(['*'])->dynamic(),
					'algorithm' => Expect::string('hmac.sha256'),
					'factory' => Expect::string(LcobucciFactory::class),
				])->required(),
				// 'debugger' => Expect::bool('%debugMode%'),
				// 'autowired' => Expect::bool(),
			]),
		)->before(fn($val): mixed => is_array(reset($val)) || reset($val) === null
			? $val
			: ['default' => $val]);
	}

	public function loadConfiguration(): void
	{
		$mercureHubsLoader = new MercureHubsDefiner($this);

		$hubDefinitions = [];
		foreach (((array) $this->getConfig()) as $name => $config) {
			$hubDefinitions[$name] = $mercureHubsLoader->hubDefinition($config, $name);
		}

		$mercureHubsLoader->postLoad($hubDefinitions);
	}

	public function beforeCompile(): void
	{
		$broadcastersLoader = new BroadcastersDefiner($this);

		$builder = $this->getContainerBuilder();

		/**
		 * @var false|ServiceDefinition $engineDefinition
		 */
		$engineDefinition = $builder->hasDefinition('latte.latteFactory')
			// @phpstan-ignore-next-line
			? $builder->getDefinition('latte.latteFactory')->getResultDefinition()
			: false;

		$broadcasterDefinitions = [];
		foreach ((array_keys((array) $this->getConfig())) as $name) {
			$broadcasterDefinitions[$name] = $broadcastersLoader->broadcasterDefinition($name, $engineDefinition);
			$broadcastersLoader->loadLinkHeaderHandler($name);
		}

		$broadcastersLoader->postLoad($broadcasterDefinitions);

		if (false !== $engineDefinition) {
			$engineDefinition
				->addSetup('addExtension', [
					new Statement(LatteMercureExtension::class, [
						new Statement(BroadcastersLoader::class, [
							$builder::literal('fn() => $this->getService(?)', [
								$this->prefix('broadcasters'),
							]),
						]),
					])
				]);
		}

		if ($builder->hasDefinition('tracy.bar')) {
			$panelDef = $builder->addDefinition($this->prefix('tracy.panel'))
				->setFactory(MercurePanel::class, [
					new Statement(BroadcastersLoader::class, [
						$builder::literal('fn() => $this->getService(?)', [
							$this->prefix('broadcasters'),
						]),
					]),
					'%hotReloadUrl%',
				])
				->setAutowired(false);

			/** @phpstan-ignore-next-line */
			$builder->getDefinition('tracy.bar')
				->addSetup('?->addPanel(?, ?)', [
					'@self',
					$panelDef,
					'mercure',
				]);
		}
	}

	public function getDebugMode(): bool
	{
		return $this->debugMode;
	}
}
