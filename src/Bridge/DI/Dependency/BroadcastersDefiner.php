<?php

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Raneomik\NetteMercure\Bridge\DI\Dependency;

use Nette\DI\ContainerBuilder;
use Nette\DI\Definitions\Definition;
use Nette\DI\Definitions\ServiceDefinition;
use Nette\DI\Definitions\Statement;
use Nette\Http\Request;
use Nette\Http\Response;
use Raneomik\NetteMercure\Bridge\DI\MercureExtension;
use Raneomik\NetteMercure\Bridge\Utils\DefinedData;
use Raneomik\NetteMercure\BroadcasterInterface;
use Raneomik\NetteMercure\Core\Broadcasters;
use Raneomik\NetteMercure\Core\JWTProvider;
use Raneomik\NetteMercure\Core\PlainBroadcaster;
use Raneomik\NetteMercure\Core\Response\Authorization;
use Raneomik\NetteMercure\Core\Response\AuthorizationInterface;
use Raneomik\NetteMercure\Core\Response\BroadcastContext;
use Raneomik\NetteMercure\Core\Response\BroadcastContextInterface;
use Raneomik\NetteMercure\Core\Response\Discovery;
use Raneomik\NetteMercure\Latte\TemplatePathResolver;
use Raneomik\NetteMercure\Latte\TemplatingBroadcaster;
use Raneomik\NetteMercure\Tracy\TraceableBroadcaster;

final class BroadcastersDefiner
{
    /**
     * @var array<string, Definition|Definition[]|false>
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

    public function loadResponseListeners(): void
    {
        /** @var false|ServiceDefinition $appDef */
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
        $requestDef = $this->definitionsCache['request']
            ??= $this->builder->getDefinitionByType(Request::class);
        $responseDef = $this->definitionsCache['response']
            ??= $this->builder->getDefinitionByType(Response::class);

        $discoveryDef = $this->definitionsCache['discovery']
            ??= $this->builder->addDefinition($this->extension->prefix('discovery'))
                ->setType(Discovery::class)
                ->setFactory(Discovery::class)
                ->setArguments([
                    $headerSerializerDef,
                    $requestDef,
                    $responseDef,
                ])
                ->setAutowired(false);
        $jwtProviderDef = $this->definitionsCache['jwtProvider']
            ??= $this->builder->addDefinition($this->extension->prefix('jwtProvider'))
                ->setType(JWTProvider::class)
                ->setFactory(JWTProvider::class)
                ->setArguments([
                    $hubRegistryDef,
                ])
                ->setAutowired(false);

        $authorizationDef = $this->definitionsCache[$this->extension->prefix('authorization')]
            ??= $this->builder->addDefinition($this->extension->prefix('authorization'))
                ->setType(AuthorizationInterface::class)
                ->setFactory(Authorization::class)
                ->setArguments([
                    $jwtProviderDef,
                    $requestDef,
                    $responseDef,
                ])
                ->setAutowired(false);

        $broadcastContextDef = $this->builder->addDefinition($this->extension->prefix('broadcastContext'))
            ->setType(BroadcastContextInterface::class)
            ->setFactory(BroadcastContext::class)
            ->setArguments([
                $authorizationDef,
                $discoveryDef,
            ]);

        $appDef->addSetup('?->onResponse[] = function() {
            ?->createCookies();
            ?->addResponseLinks();
        }', [
            '@self',
            $broadcastContextDef,
            $broadcastContextDef,
        ]);
    }

    public function broadcasterDefinition(\stdClass $hubConfig, string $hubName, false|ServiceDefinition $latteDefinition): Definition
    {
        $defaultsDef = $this->builder->addDefinition(
            $this->extension->prefix($this->extension->prefix(sprintf('defaults.%s', $hubName)))
        )
            ->setType(DefinedData::class)
            ->setFactory(DefinedData::class, [
                $hubConfig->url ?? null,
                $hubConfig->jwt->subscribe ?? [],
                $hubConfig->jwt->publish ?? [],
                $hubConfig->jwt->noCookie ?? false,
            ])
            ->setAutowired(false);

        $plainBroadcasterDef = $this->builder->addDefinition($this->extension->prefix(sprintf('broadcaster.%s.plain', $hubName)))
            ->setType(
                false !== $latteDefinition && $this->debugMode
                    ? PlainBroadcaster::class
                    : BroadcasterInterface::class
            )
            ->setFactory(PlainBroadcaster::class, [
                $this->builder->getDefinition($this->extension->prefix('sf.hub.' . $hubName)),
                $this->builder->hasDefinition($this->extension->prefix('broadcastContext'))
                    /** @phpstan-ignore-next-line  */
                    ? $this->builder->getDefinition($this->extension->prefix('broadcastContext'))
                        ->addSetup('?->addData(?, ?)', [
                            '@self',
                            $hubName,
                            $defaultsDef,
                        ])
                    : null,
            ])
            ->setAutowired(false);

        $latteBroadcasterDef = null;
        if (false !== $latteDefinition) {
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
        if ($this->debugMode && $hubConfig->debugger) {
            $broadcasterDefinition = $this->builder->addDefinition(
                $this->extension->prefix(sprintf('broadcaster.%s.traceable', $hubName))
            )
                ->setType(TraceableBroadcaster::class)
                ->setFactory(TraceableBroadcaster::class, [
                    $latteBroadcasterDef ?? $plainBroadcasterDef,
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
        $this->builder->addDefinition('latte.templatePathResolver')
            ->setType(TemplatePathResolver::class)
            ->setFactory(TemplatePathResolver::class)
            ->setAutowired(false);

        $this->builder->addDefinition($this->extension->prefix('broadcasters'))
            ->setType(BroadcasterInterface::class)
            ->setFactory(Broadcasters::class, [
                $broadcasterDefinitions,
            ])
            ->setAutowired();
    }
}
