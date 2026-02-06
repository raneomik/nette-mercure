<?php

declare(strict_types=1);

namespace Raneomik\NetteMercure\Bridge\DI\Dependency;

use Nette\DI\ContainerBuilder;
use Nette\DI\Definitions\Definition;
use Nette\DI\Definitions\ServiceDefinition;
use Nette\DI\Definitions\Statement;
use Raneomik\NetteMercure\Bridge\DI\MercureExtension;
use Raneomik\NetteMercure\Bridge\Utils\ConfiguredData;
use Raneomik\NetteMercure\Bridge\Utils\ConfiguredDataRegistry;
use Raneomik\NetteMercure\BroadcasterInterface;
use Raneomik\NetteMercure\Core\Publish\Broadcasters;
use Raneomik\NetteMercure\Core\Publish\Latte\TemplatePathResolver;
use Raneomik\NetteMercure\Core\Publish\Latte\TemplatingBroadcaster;
use Raneomik\NetteMercure\Core\Publish\PlainBroadcaster;
use Raneomik\NetteMercure\Core\Publish\Tracy\TraceableBroadcaster;

final readonly class BroadcastersDefiner
{
    private ContainerBuilder $builder;

    private bool $debugMode;

    public function __construct(
        private MercureExtension $extension,
    ) {
        $this->builder = $extension->getContainerBuilder();
        $this->debugMode = $extension->getDebugMode();
    }

    public function broadcasterDefinition(\stdClass $hubConfig, string $hubName, false|ServiceDefinition $latteDefinition): Definition
    {
        $plainBroadcasterDef = $this->builder->addDefinition($this->extension->prefix(\sprintf('broadcaster.%s.plain', $hubName)))
            ->setType(
                false !== $latteDefinition && $this->debugMode
                    ? PlainBroadcaster::class
                    : BroadcasterInterface::class
            )
            ->setFactory(PlainBroadcaster::class, [
                $this->builder->getDefinition($this->extension->prefix('sf.hub.'.$hubName)),
            ])
            ->setAutowired(false)
        ;

        $latteBroadcasterDef = null;
        if (false !== $latteDefinition) {
            $latteBroadcasterDef = $this->builder->addDefinition($this->extension->prefix(\sprintf('broadcaster.%s.latte', $hubName)))
                ->setType(TemplatingBroadcaster::class)
                ->setFactory(TemplatingBroadcaster::class)
                ->setArguments([
                    $plainBroadcasterDef,
                    '@latte.templatePathResolver',
                    new Statement('@latte.latteFactory::create'),
                ])
                ->setAutowired(false)
            ;
        }

        $broadcasterDefinition = null;
        if ($this->debugMode && $hubConfig->debugger) {
            $broadcasterDefinition = $this->builder->addDefinition(
                $this->extension->prefix(\sprintf('broadcaster.%s.traceable', $hubName))
            )
                ->setType(TraceableBroadcaster::class)
                ->setFactory(TraceableBroadcaster::class, [
                    $latteBroadcasterDef ?? $plainBroadcasterDef,
                ])
                ->setAutowired(false)
            ;
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
            ->setAutowired(false)
        ;

        $this->builder->addDefinition($this->extension->prefix('broadcasters'))
            ->setType(BroadcasterInterface::class)
            ->setFactory(Broadcasters::class, [
                $broadcasterDefinitions,
            ])
            ->setAutowired()
        ;

        $configDefinitions = [];
        foreach ((array) $this->extension->getConfig() as $name => $config) {
            $configDefinitions[$name] = new Statement(
                ConfiguredData::class,
                [
                    $config->url,
                    $config->jwt->subscribe ?? [],
                    $config->jwt->publish ?? [],
                    $config->jwt->noCookie ?? false,
                ]
            );
        }

        $this->builder->addDefinition($this->extension->prefix('hubsConfiguration'))
            ->setType(ConfiguredDataRegistry::class)
            ->setFactory(ConfiguredDataRegistry::class, [
                $configDefinitions,
            ])
            ->setAutowired(false)
        ;
    }
}
