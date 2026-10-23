<?php

declare(strict_types=1);

namespace Raneomik\NetteMercure\Bridge\DI;

use Nette;
use Nette\DI\Definitions\ServiceDefinition;
use Nette\DI\Definitions\Statement;
use Nette\Schema\Expect;
use Raneomik\NetteMercure\Bridge\DI\Config\Configuration;
use Raneomik\NetteMercure\Bridge\DI\Config\ConfigurationRegistry;
use Raneomik\NetteMercure\Bridge\DI\Dependency\BroadcastersDefiner;
use Raneomik\NetteMercure\Bridge\DI\Dependency\MercureHubsDefiner;
use Raneomik\NetteMercure\Bridge\DI\Dependency\SubscribersDefiner;
use Raneomik\NetteMercure\Bridge\Latte\MercureExtension as LatteMercureExtension;
use Raneomik\NetteMercure\Bridge\Tracy\MercurePanel;
use Raneomik\NetteMercure\Bridge\Utils\BroadcastersLoader;
use Symfony\Component\Mercure\ProtocolVersion;

final class MercureExtension extends Nette\DI\CompilerExtension
{
    private const array HUB_OPTIONS = ['url', 'publicUrl', 'protocolVersion', 'jwt', 'useCookie', 'autoDiscovery', 'debugger'];

    public function __construct(
        private readonly bool $debugMode = false,
        private ?string $hotReloadUrl = null,
    ) {
        $this->hotReloadUrl ??= ($_SERVER['FRANKENPHP_HOT_RELOAD'] ?? null);
    }

    #[\Override]
    public function getConfigSchema(): Nette\Schema\Schema
    {
        return Expect::arrayOf(
            Expect::structure([
                'url' => Expect::string()->default('%baseUrl%/.well-known/mercure')->dynamic(),
                'publicUrl' => Expect::string()->nullable()->dynamic(),
                'protocolVersion' => Expect::anyOf(...array_map(
                    static fn (ProtocolVersion $version): string => $version->value,
                    ProtocolVersion::cases(),
                ))
                    ->default(ProtocolVersion::V1->value),
                'jwt' => Expect::structure([
                    // "key: <secret>" is a shorthand for "key: {secret: <secret>}"
                    'key' => Expect::structure([
                        'secret' => Expect::string()->required()->dynamic(),
                        'passphrase' => Expect::string()->nullable()->dynamic(),
                        'algorithm' => Expect::string()->nullable(),
                    ])->required()
                        ->before(static fn (mixed $key): mixed => \is_array($key) ? $key : [
                            'secret' => $key,
                        ]),
                    'publish' => Expect::arrayOf('string|string[]')->default(['*'])->mergeDefaults(false),
                    'subscribe' => Expect::arrayOf('string|string[]')->default(['*'])->mergeDefaults(false),
                    'factory' => Expect::string()->nullable(),
                    'lifetime' => Expect::int(0)->nullable()->dynamic(),
                    'useQueryParam' => Expect::bool(false),
                    'claims' => Expect::structure([
                        'iss' => Expect::string()->nullable()->dynamic(),
                        'aud' => Expect::string()->nullable()->dynamic(),
                        'sub' => Expect::string()->nullable()->dynamic(),
                        'clientId' => Expect::string()->nullable()->dynamic(),
                        'additional' => Expect::arrayOf('mixed', 'string')->default([]),
                    ]),
                ])->required(),
                'useCookie' => Expect::bool(true),
                'autoDiscovery' => Expect::bool(true),
                'debugger' => Expect::bool($this->debugMode),
            ]),
        )->before(static fn (mixed $val): mixed => \is_array($val) && [] !== array_intersect_key($val, array_flip(self::HUB_OPTIONS))
            // a single hub configuration, rather than a list of named hubs
            ? [
                'default' => $val,
            ]
            : $val);
    }

    public function beforeCompile(): void
    {
        $broadcastersLoader = new BroadcastersDefiner($this);

        $builder = $this->getContainerBuilder();

        /** @var false|ServiceDefinition $latteDefinition */
        $latteDefinition = $builder->hasDefinition('latte.latteFactory')
            ? $builder->getDefinition('latte.latteFactory')
                ->getResultDefinition() // @phpstan-ignore method.notFound
            : false;

        $debug = false;
        $configDefinitions = [];
        $broadcasterDefinitions = [];
        foreach ((array) $this->getConfig() as $hubName => $config) {
            $configDefinitions[$hubName] = new Statement(
                Configuration::class,
                [
                    $hubName,
                    $config->url,
                    $config->publicUrl ?? $config->url,
                    $config->jwt->subscribe,
                    $config->jwt->publish,
                    $config->jwt->useQueryParam,
                    ProtocolVersion::from($config->protocolVersion),
                    MercureHubsDefiner::claimsStatement($config),
                    $config->useCookie,
                    $config->autoDiscovery,
                ]
            );

            $broadcasterDefinitions[$hubName] = $broadcastersLoader->broadcasterDefinition($config, $hubName, $latteDefinition);

            $debug = $debug || $config->debugger;
        }

        $builder->addDefinition($this->prefix('hubsConfiguration'))
            ->setType(ConfigurationRegistry::class)
            ->setFactory(ConfigurationRegistry::class, [
                $configDefinitions,
            ])
            ->setAutowired(false)
        ;

        $subscribersDefiner = new SubscribersDefiner($this);
        $subscribersDefiner->defineSubscriptionComponents();

        $broadcastersLoader->postLoad($broadcasterDefinitions);

        if (false !== $latteDefinition) {
            $latteDefinition
                ->addSetup('addExtension', [
                    new Statement(LatteMercureExtension::class, [
                        '@' . $this->prefix('jwtProvider'),
                        new Statement(BroadcastersLoader::class, [
                            $builder::literal('fn() => $this->getService(?)', [
                                $this->prefix('broadcasters'),
                            ]),
                        ]),
                        '@' . $this->prefix('hubsConfiguration'),
                        $this->hotReloadUrl,
                    ]),
                ])
            ;
        }

        if ($debug && $builder->hasDefinition('tracy.bar')) {
            $panelArgs = [
                new Statement(BroadcastersLoader::class, [
                    $builder::literal('fn() => $this->getService(?)', [
                        $this->prefix('broadcasters'),
                    ]),
                ]),
                '@' . $this->prefix('jwtProvider'),
                '@' . $this->prefix('hubsConfiguration'),
                $this->hotReloadUrl,
                null,
            ];

            $panelDef = $builder->addDefinition($this->prefix('tracy.panel'))
                ->setFactory(MercurePanel::class, $panelArgs)
                ->setAutowired(false)
            ;

            $builder->getDefinition('tracy.bar')
                // @phpstan-ignore-next-line
                ->addSetup('?->addPanel(?, ?)', [
                    '@self',
                    $panelDef,
                    'mercure',
                ])
            ;
        }
    }

    public function loadConfiguration(): void
    {
        $mercureHubsLoader = new MercureHubsDefiner($this);

        $hubDefinitions = [];
        foreach ((array) $this->getConfig() as $name => $config) {
            $hubDefinitions[$name] = $mercureHubsLoader->hubDefinition($config, $name);
        }

        $mercureHubsLoader->postLoad($hubDefinitions);
    }
}
