<?php

declare(strict_types=1);

namespace Raneomik\NetteMercure\Bridge\DI\Dependency;

use Nette\DI\ContainerBuilder;
use Nette\DI\Definitions\Definition;
use Nette\DI\Definitions\Statement;
use Nette\DI\InvalidConfigurationException;
use Raneomik\NetteMercure\Bridge\DI\Config\Claims;
use Raneomik\NetteMercure\Bridge\DI\Config\SigningKey;
use Raneomik\NetteMercure\Bridge\DI\MercureExtension;
use Raneomik\NetteMercure\Bridge\Utils\HubFactory;
use Raneomik\NetteMercure\Bridge\Utils\TokenFactoryFactory;
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

    public function __construct(
        private MercureExtension $extension,
    ) {
        $this->builder = $extension->getContainerBuilder();
    }

    /**
     * @param array<string, Definition> $broadcasterDefinitions
     */
    public function postLoad(array $broadcasterDefinitions): void
    {
        $this->builder->addDefinition($this->extension->prefix('symfony.hub.registry'))
            ->setType(HubRegistry::class)
            ->setCreator(HubRegistry::class, [
                array_first($broadcasterDefinitions),
                $broadcasterDefinitions,
            ])
            ->setAutowired(false)
        ;

        $this->builder->addDefinition($this->extension->prefix('symfony.links.headerSerializer'))
            ->setType(HttpHeaderSerializer::class)
            ->setCreator(HttpHeaderSerializer::class, [])
            ->setAutowired(false)
        ;
    }

    public function hubDefinition(\stdClass $config, string $name): Definition
    {
        $protocol = ProtocolVersion::from($config->protocolVersion);
        $publicUrl = $config->publicUrl ?? $config->url;

        $decoratedFactoryDefinition = $this->builder->addDefinition(
            $this->extension->prefix('token.factory.decorated.' . $name)
        )
            ->setType(TokenFactoryInterface::class)
            ->setCreator($this->tokenFactoryStatement($config->jwt, $protocol, $name))
            ->setAutowired(false)
        ;

        $tokenFactoryDefinition = $this->builder->addDefinition($this->extension->prefix('token.factory.' . $name))
            ->setType(TokenFactoryInterface::class)
            ->setCreator(new Statement(DefaultClaimsTokenFactory::class, [
                $decoratedFactoryDefinition,
                new Statement([self::claimsStatement($config), 'toArray']),
            ]))
            ->setAutowired(false)
        ;

        $factoryProviderDefinition = $this->builder->addDefinition(
            $this->extension->prefix('token.provider.' . $name)
        )
            ->setType(TokenProviderInterface::class)
            ->setCreator(new Statement(FactoryTokenProvider::class, [
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

        return $this->builder->addDefinition($this->extension->prefix('sf.hub.' . $name))
            ->setType(HubInterface::class)
            ->setCreator(new Statement([HubFactory::class, 'create'], [
                'url' => $config->url,
                'jwtProvider' => $factoryProviderDefinition,
                'jwtFactory' => $tokenFactoryDefinition,
                'publicUrl' => $publicUrl,
                'protocolVersion' => $protocol,
            ]))
            ->setAutowired(false)
        ;
    }

    /**
     * Claims are built at runtime, as they may come from dynamic parameters.
     * With the Mercure protocol 1.0, missing RFC 9068 claims fall back to defaults.
     */
    public static function claimsStatement(\stdClass $config): Statement
    {
        $claims = new Statement(Claims::class, [
            'iss' => $config->jwt->claims->iss,
            'aud' => $config->jwt->claims->aud,
            'sub' => $config->jwt->claims->sub,
            'clientId' => $config->jwt->claims->clientId,
            'additional' => $config->jwt->claims->additional,
        ]);

        return ProtocolVersion::V1 === ProtocolVersion::from($config->protocolVersion)
            ? new Statement([$claims, 'withPublicUrl'], [$config->publicUrl ?? $config->url])
            : $claims;
    }

    private function tokenFactoryStatement(\stdClass $jwt, ProtocolVersion $protocol, string $name): Statement
    {
        $factory = $jwt->factory ?? (ProtocolVersion::V1 === $protocol ? WebTokenFactory::class : LcobucciFactory::class);

        if (WebTokenFactory::class === $factory && ProtocolVersion::V1 !== $protocol) {
            throw new InvalidConfigurationException(\sprintf(
                'Hub "%s": %s only supports the Mercure protocol 1.0, use %s for the legacy protocol.',
                $name,
                WebTokenFactory::class,
                LcobucciFactory::class,
            ));
        }

        return new Statement([TokenFactoryFactory::class, 'create'], [
            'key' => new Statement(SigningKey::class, [
                'secret' => $jwt->key->secret,
                'passphrase' => $jwt->key->passphrase,
                'algorithm' => $jwt->key->algorithm,
            ]),
            'factory' => $factory,
            'lifetime' => $jwt->lifetime,
            'protocolVersion' => $protocol,
        ]);
    }
}
