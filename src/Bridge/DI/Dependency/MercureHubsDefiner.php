<?php

declare(strict_types=1);

namespace Raneomik\NetteMercure\Bridge\DI\Dependency;

use Nette\DI\ContainerBuilder;
use Nette\DI\Definitions\Definition;
use Nette\DI\Definitions\Statement;
use Raneomik\NetteMercure\Bridge\DI\MercureExtension;
use Symfony\Component\Mercure\FrankenPhpHub;
use Symfony\Component\Mercure\Hub;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\HubRegistry;
use Symfony\Component\Mercure\Jwt\FactoryTokenProvider;
use Symfony\Component\Mercure\Jwt\LcobucciFactory;
use Symfony\Component\Mercure\Jwt\TokenFactoryInterface;
use Symfony\Component\Mercure\Jwt\TokenProviderInterface;
use Symfony\Component\WebLink\HttpHeaderSerializer;

final readonly class MercureHubsDefiner
{
    private ContainerBuilder $builder;

    private bool $debugMode;

    public function __construct(
        private MercureExtension $extension,
    ) {
        $this->builder = $extension->getContainerBuilder();
        $this->debugMode = $extension->getDebugMode();
    }

    /**
     * @param array<string, Definition> $broadcasterDefinitions
     */
    public function postLoad(array $broadcasterDefinitions): void
    {
        $this->builder->addDefinition($this->extension->prefix('symfony.hub.registry'))
            ->setType(HubRegistry::class)
            ->setFactory(HubRegistry::class, [
                array_first($broadcasterDefinitions),
                $broadcasterDefinitions,
            ])
            ->setAutowired(false)
        ;

        $this->builder->addDefinition($this->extension->prefix('symfony.links.headerSerializer'))
            ->setType(HttpHeaderSerializer::class)
            ->setFactory(HttpHeaderSerializer::class, [])
            ->setAutowired(false)
        ;
    }

    public function hubDefinition(\stdClass $config, string $name): Definition
    {
        $factoryArguments = LcobucciFactory::class === $config->jwt->factory ? [
            $config->jwt->secret,
            $config->jwt->algorithm,
            $config->jwt->lifetime,
        ] : [
            $config->jwt->secret,
        ];

        $tokenFactoryDefinition = $this->builder->addDefinition($this->extension->prefix('token.factory.'.$name))
            ->setType(TokenFactoryInterface::class)
            ->setFactory(new Statement($config->jwt->factory, $factoryArguments))
            ->setAutowired(false)
        ;

        $hubAlias = $this->extension->prefix('sf.hub.'.$name);
        if (getenv('FRANKENPHP_CONFIG') ?: false) {
            return $this->builder->addDefinition($hubAlias)
                ->setType($this->debugMode ? FrankenPhpHub::class : HubInterface::class)
                ->setFactory(FrankenPhpHub::class, [
                    $config->url,
                    $tokenFactoryDefinition,
                ])
                ->setAutowired(false)
            ;
        }

        $factoryProviderDefinition = $this->builder->addDefinition($this->extension->prefix('token.provider.'.$name))
            ->setType(TokenProviderInterface::class)
            ->setFactory(new Statement(FactoryTokenProvider::class, [
                $tokenFactoryDefinition,
                $config->jwt->subscribe,
                $config->jwt->publish,
            ]))
            ->setAutowired(false)
        ;

        return $this->builder->addDefinition($hubAlias)
            ->setType($this->debugMode ? Hub::class : HubInterface::class)
            ->setFactory(new Statement(Hub::class, [
                $config->url,
                $factoryProviderDefinition,
                $tokenFactoryDefinition,
            ]))
            ->setAutowired(false)
        ;
    }
}
