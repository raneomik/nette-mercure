<?php

declare(strict_types=1);

namespace Raneomik\NetteMercure\Bridge\DI\Dependency;

use Nette\DI\ContainerBuilder;
use Nette\DI\Definitions\Definition;
use Nette\DI\Definitions\Statement;
use Nette\DI\InvalidConfigurationException;
use Raneomik\NetteMercure\Bridge\DI\Config\Claims;
use Raneomik\NetteMercure\Bridge\DI\MercureExtension;
use Symfony\Component\Mercure\FrankenPhpHub;
use Symfony\Component\Mercure\Hub;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\HubRegistry;
use Symfony\Component\Mercure\Jwt\DefaultClaimsTokenFactory;
use Symfony\Component\Mercure\Jwt\FactoryTokenProvider;
use Symfony\Component\Mercure\Jwt\Grant;
use Symfony\Component\Mercure\Jwt\LcobucciFactory;
use Symfony\Component\Mercure\Jwt\TokenFactoryInterface;
use Symfony\Component\Mercure\Jwt\TokenProviderInterface;
use Symfony\Component\Mercure\Jwt\WebTokenFactory;
use Symfony\Component\Mercure\ProtocolVersion;
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
        $protocol = ProtocolVersion::from($config->protocolVersion);
        $publicUrl = $config->publicUrl ?? $config->url;

        $decoratedFactoryDefinition = $this->builder->addDefinition($this->extension->prefix('token.factory.decorated.' . $name))
            ->setType(TokenFactoryInterface::class)
            ->setFactory($this->tokenFactoryStatement($config->jwt, $protocol, $name))
            ->setAutowired(false)
        ;

        $tokenFactoryDefinition = $this->builder->addDefinition($this->extension->prefix('token.factory.' . $name))
            ->setType(TokenFactoryInterface::class)
            ->setFactory(new Statement(DefaultClaimsTokenFactory::class, [
                $decoratedFactoryDefinition,
                ProtocolVersion::V1 === $protocol
                    ? $config->jwt->claims + Claims::defaults()
                    : $config->jwt->claims,
            ]))
            ->setAutowired(false)
        ;

        $hubAlias = $this->extension->prefix('sf.hub.' . $name);
        if (getenv('FRANKENPHP_SITE_CONFIG') ?: getenv('FRANKENPHP_CONFIG') ?: false) {
            return $this->builder->addDefinition($hubAlias)
                ->setType($this->debugMode ? FrankenPhpHub::class : HubInterface::class)
                ->setFactory(FrankenPhpHub::class, [
                    'publicUrl' => $publicUrl,
                    'jwtFactory' => $tokenFactoryDefinition,
                    'protocolVersion' => $protocol,
                ])
                ->setAutowired(false)
            ;
        }

        $factoryProviderDefinition = $this->builder->addDefinition($this->extension->prefix('token.provider.' . $name))
            ->setType(TokenProviderInterface::class)
            ->setFactory(new Statement(FactoryTokenProvider::class, [
                $tokenFactoryDefinition,
                [
                    new Statement(Grant::class, [
                        [Grant::ACTION_SUBSCRIBE],
                        $config->jwt->subscribe,
                    ]),
                    new Statement(Grant::class, [
                        [Grant::ACTION_PUBLISH],
                        $config->jwt->publish,
                    ]),
                ],
            ]))
            ->setAutowired(false)
        ;

        return $this->builder->addDefinition($hubAlias)
            ->setType($this->debugMode ? Hub::class : HubInterface::class)
            ->setFactory(new Statement(Hub::class, [
                'url' => $config->url,
                'jwtProvider' => $factoryProviderDefinition,
                'jwtFactory' => $tokenFactoryDefinition,
                'publicUrl' => $publicUrl,
                'protocolVersion' => $protocol,
            ]))
            ->setAutowired(false)
        ;
    }

    private function tokenFactoryStatement(\stdClass $jwt, ProtocolVersion $protocol, string $name): Statement
    {
        $factory = $jwt->factory ?? (ProtocolVersion::V1 === $protocol ? WebTokenFactory::class : LcobucciFactory::class);

        return match ($factory) {
            LcobucciFactory::class => new Statement(LcobucciFactory::class, [
                'secret' => $jwt->secret,
                'algorithm' => $jwt->algorithm ?? 'hmac.sha256',
                'jwtLifetime' => $jwt->lifetime,
                'passphrase' => $jwt->passphrase,
                'protocolVersion' => $protocol,
            ]),
            WebTokenFactory::class => ProtocolVersion::V1 === $protocol
                ? new Statement([WebTokenFactory::class, 'fromSecret'], [
                    'secret' => $jwt->secret,
                    'algorithm' => $jwt->algorithm ?? 'HS256',
                    'jwtLifetime' => $jwt->lifetime,
                    'passphrase' => $jwt->passphrase,
                ])
                : throw new InvalidConfigurationException(\sprintf(
                    'Hub "%s": %s only supports the Mercure protocol 1.0, use %s for the legacy protocol.',
                    $name,
                    WebTokenFactory::class,
                    LcobucciFactory::class,
                )),
            default => new Statement($factory, [$jwt->secret]),
        };
    }
}
