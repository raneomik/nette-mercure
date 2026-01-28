<?php

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Nette\Mercure\Bridge\DI\Dependency;

use Nette\DI\ContainerBuilder;
use Nette\DI\Definitions\Definition;
use Nette\DI\Definitions\ServiceDefinition;
use Nette\DI\Definitions\Statement;
use Nette\Http\Request;
use Nette\Http\Response;
use Nette\Mercure\Bridge\DI\MercureExtension;
use Nette\Mercure\BroadcasterInterface;
use Nette\Mercure\Core\AddLinkHeaderHandler;
use Nette\Mercure\Core\Broadcasters;
use Nette\Mercure\Core\PlainBroadcaster;
use Nette\Mercure\Latte\TemplatePathResolver;
use Nette\Mercure\Latte\TemplatingBroadcaster;
use Nette\Mercure\Tracy\TraceableBroadcaster;

final class BroadcastersDefiner
{
	/**
	 *
	 * @var array<string, Definition|false>
	 */
	private array $definitionsCache = [];

	private readonly ContainerBuilder $builder;

	private readonly bool $debugMode;

	public function __construct(
		private readonly MercureExtension $extension,
	) {
		$this->builder = $extension->getContainerBuilder();
		$this->debugMode = $extension->getDebugMode();
	}

	public function broadcasterDefinition(string $hubName, false|ServiceDefinition $engineDefinition): Definition
	{
		$plainBroadcasterDef = $this->builder->addDefinition($this->extension->prefix(sprintf('broadcaster.%s.plain', $hubName)))
			->setType(
				false !== $engineDefinition && $this->debugMode
					? PlainBroadcaster::class
					: BroadcasterInterface::class
			)
			->setFactory(PlainBroadcaster::class, [$this->builder->getDefinition($this->extension->prefix('sf.hub.' . $hubName))])
			->setAutowired(false);

		$latteBroadcasterDef = null;
		if (false !== $engineDefinition) {
			$latteBroadcasterDef = $this->builder->addDefinition($this->extension->prefix(sprintf('broadcaster.%s.latte', $hubName)))
				->setType(TemplatingBroadcaster::class)
				->setFactory(TemplatingBroadcaster::class)
				->setArguments([
					$plainBroadcasterDef,
					'@latte.templatePathResolver',
					new Statement('@latte.latteFactory::create'),
				])
				->setAutowired(false);
		}

		$broadcasterDefinition = null;
		if ($this->debugMode) {
			$broadcasterDefinition = $this->builder->addDefinition($this->extension->prefix(sprintf('broadcaster.%s.traceable', $hubName)))
				->setType(TraceableBroadcaster::class)
				->setFactory(TraceableBroadcaster::class, [
					$latteBroadcasterDef ?? $plainBroadcasterDef,
					'@latte.templatePathResolver',
				])
				->setAutowired(false);
		}

		return $broadcasterDefinition ?? $latteBroadcasterDef ?? $plainBroadcasterDef;
	}

	/**
	 * @param array<string, Definition> $broadcasterDefinitions
	 */
	public function postLoad(array $broadcasterDefinitions): void
	{
		$this->builder->addDefinition("latte.templatePathResolver")
			->setType(TemplatePathResolver::class)
			->setFactory(TemplatePathResolver::class)
			->setAutowired(false);

		$this->builder->addDefinition($this->extension->prefix('broadcasters'))
			->setType(BroadcasterInterface::class)
			->setFactory(Broadcasters::class, [
				$broadcasterDefinitions,
			])
			->setAutowired(true);
	}

	public function loadLinkHeaderHandler(string $hubName): void
	{
		/** @var false|ServiceDefinition */
		$appDef = $this->definitionsCache['app'] ??= (
			$this->builder->hasDefinition('application.application')
			? $this->builder->getDefinition('application.application')
			: false
		);

		if (false === $appDef) {
			return;
		}

		$hubRegistryDef = $this->definitionsCache['symfony.hub.registry']
			??= $this->builder->getDefinition($this->extension->prefix('symfony.hub.registry'));
		$headerSerializerDef = $this->definitionsCache['symfony.links.headerSerializer']
			??= $this->builder->getDefinition($this->extension->prefix('symfony.links.headerSerializer'));
		$requestDef = $this->definitionsCache['request'] ??= $this->builder->getDefinitionByType(Request::class);
		$responseDef = $this->definitionsCache['response'] ??= $this->builder->getDefinitionByType(Response::class);

		$linkAdditionDef = $this->builder->addDefinition($this->extension->prefix('preflight.' . $hubName))
			->setType(AddLinkHeaderHandler::class)
			->setFactory(AddLinkHeaderHandler::class)
			->setArguments([
				$hubRegistryDef,
				$headerSerializerDef,
				$requestDef,
				$responseDef,
				$hubName,
			])
			->setAutowired(false);

		$appDef->addSetup('?->onRequest[] = ?', [
			'@self',
			$linkAdditionDef,
		]);
	}
}
