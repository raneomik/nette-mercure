<?php

declare(strict_types=1);

namespace Raneomik\NetteMercure\Bridge\DI\Dependency;

use Nette\DI\ContainerBuilder;
use Nette\DI\Definitions\ServiceDefinition;
use Nette\Http\Request;
use Nette\Http\Response;
use Raneomik\NetteMercure\Bridge\DI\MercureExtension;
use Raneomik\NetteMercure\Core\Discovery;
use Raneomik\NetteMercure\Core\Subscribe\Authorization;
use Raneomik\NetteMercure\Core\Subscribe\AuthorizationInterface;
use Raneomik\NetteMercure\Core\Subscribe\JWTProvider;
use Raneomik\NetteMercure\Core\Subscribe\JWTProviderInterface;
use Raneomik\NetteMercure\Core\Subscribe\Subscriber;
use Raneomik\NetteMercure\SubscriberInterface;

final readonly class SubscribersDefiner
{
    private ContainerBuilder $builder;

    public function __construct(
        private MercureExtension $extension,
    ) {
        $this->builder = $extension->getContainerBuilder();
    }

    public function defineSubscriptionComponents(): void
    {
        /** @var false|ServiceDefinition $appDef */
        $appDef = $this->builder->hasDefinition('application.application')
            ? $this->builder->getDefinition('application.application')
            : false;

        if (false === $appDef) {
            return;
        }

        $jwtProviderDef = $this->builder->addDefinition($this->extension->prefix('jwtProvider'))
            ->setType(JWTProviderInterface::class)
            ->setFactory(JWTProvider::class)
            ->setArguments([
                $this->builder->getDefinition($this->extension->prefix('symfony.hub.registry')),
            ])
            ->setAutowired()
        ;

        $requestDef = $this->builder->getDefinitionByType(Request::class);
        $responseDef = $this->builder->getDefinitionByType(Response::class);

        $authorizationDef = $this->builder->addDefinition($this->extension->prefix('authorization'))
            ->setType(AuthorizationInterface::class)
            ->setFactory(Authorization::class)
            ->setArguments([
                $jwtProviderDef,
                $requestDef,
                $responseDef,
            ])
            ->setAutowired()
        ;

        $discoveryDef = $this->builder->addDefinition($this->extension->prefix('discovery'))
            ->setType(Discovery::class)
            ->setFactory(Discovery::class)
            ->setArguments([
                $this->builder->getDefinition($this->extension->prefix('symfony.links.headerSerializer')),
                $requestDef,
                $responseDef,
            ])
            ->setAutowired()
        ;

        $this->builder->addDefinition($this->extension->prefix('subscriber'))
            ->setType(SubscriberInterface::class)
            ->setFactory(Subscriber::class)
            ->setArguments([
                $authorizationDef,
                $jwtProviderDef,
                $discoveryDef,
                '@'.$this->extension->prefix('hubsConfiguration'),
            ])
            ->setAutowired()
        ;
    }
}
