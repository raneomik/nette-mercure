<?php

declare(strict_types=1);

namespace Raneomik\NetteMercure\Bridge\DI\Dependency;

use Nette\Application\LinkGenerator;
use Nette\DI\ContainerBuilder;
use Nette\DI\Definitions\ServiceDefinition;
use Nette\DI\Definitions\Statement;
use Raneomik\NetteMercure\Bridge\DI\MercureExtension;
use Raneomik\NetteMercure\BroadcasterInterface;
use Raneomik\NetteMercure\Core\Publish\Broadcasters;
use Raneomik\NetteMercure\Core\Publish\Latte\TemplatePathResolver;
use Raneomik\NetteMercure\Core\Publish\Latte\TemplatingBroadcaster;
use Raneomik\NetteMercure\Core\Publish\LocalizedBroadcaster;
use Raneomik\NetteMercure\Core\Publish\PlainBroadcaster;
use Raneomik\NetteMercure\Core\Publish\Tracy\TraceableBroadcaster;

final readonly class BroadcastersDefiner
{
    private ContainerBuilder $builder;

    public function __construct(
        private MercureExtension $extension,
    ) {
        $this->builder = $extension->getContainerBuilder();
    }

    public function broadcasterDefinition(
        \stdClass $hubConfig,
        string $hubName,
        false|ServiceDefinition $latteDefinition,
        ?string $localeSwitcher = null,
    ): ServiceDefinition {
        $plainBroadcasterDef = $this->builder->addDefinition($this->extension->prefix(\sprintf('broadcaster.%s.plain', $hubName)))
            ->setType(PlainBroadcaster::class)
            ->setCreator(PlainBroadcaster::class, [
                $this->builder->getDefinition($this->extension->prefix('sf.hub.' . $hubName)),
            ])
            ->setAutowired(false)
        ;

        $latteBroadcasterDef = null;
        if (false !== $latteDefinition) {
            // null as well when nette/application is not installed
            $linkGeneratorName = $this->builder->getByType(LinkGenerator::class);

            $latteBroadcasterDef = $this->builder->addDefinition($this->extension->prefix(\sprintf('broadcaster.%s.latte', $hubName)))
                ->setType(TemplatingBroadcaster::class)
                ->setCreator(TemplatingBroadcaster::class)
                ->setArguments([
                    $plainBroadcasterDef,
                    '@latte.templatePathResolver',
                    new Statement('@latte.latteFactory::create'),
                ] + (null === $linkGeneratorName ? [] : [
                    'linkGenerator' => '@' . $linkGeneratorName,
                ]))
                ->setAutowired(false)
            ;
        }

        $broadcasterDefinition = null;
        if ($hubConfig->debugger) {
            $broadcasterDefinition = $this->builder->addDefinition(
                $this->extension->prefix(\sprintf('broadcaster.%s.traceable', $hubName))
            )
                ->setType(TraceableBroadcaster::class)
                ->setCreator(TraceableBroadcaster::class, [
                    $latteBroadcasterDef ?? $plainBroadcasterDef,
                    'hubName' => $hubName,
                ])
                ->setAutowired(false)
            ;
        }

        $broadcasterDefinition ??= $latteBroadcasterDef ?? $plainBroadcasterDef;

        if ($hubConfig->localized && null !== $localeSwitcher) {
            // outermost: each rendering is traced on its own, with its locale
            $broadcasterDefinition = $this->builder->addDefinition(
                $this->extension->prefix(\sprintf('broadcaster.%s.localized', $hubName))
            )
                ->setType(LocalizedBroadcaster::class)
                ->setCreator(LocalizedBroadcaster::class, [
                    $broadcasterDefinition,
                    '@' . $localeSwitcher,
                ])
                ->setAutowired(false)
            ;
        }

        return $broadcasterDefinition;
    }

    /**
     * @param array<string, ServiceDefinition> $broadcasterDefinitions
     */
    public function postLoad(array $broadcasterDefinitions): void
    {
        $this->builder->addDefinition('latte.templatePathResolver')
            ->setType(TemplatePathResolver::class)
            ->setCreator(TemplatePathResolver::class)
            ->setAutowired(false)
        ;

        $this->builder->addDefinition($this->extension->prefix('broadcasters'))
            ->setType(BroadcasterInterface::class)
            ->setCreator(Broadcasters::class, [
                $broadcasterDefinitions,
            ])
            ->setAutowired()
        ;
    }
}
