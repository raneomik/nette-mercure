<?php

declare(strict_types=1);

namespace Raneomik\NetteMercure\Bridge\DI;

use Nette;
use Nette\DI\Definitions\ServiceDefinition;
use Nette\DI\Definitions\Statement;
use Nette\Schema\Expect;
use Raneomik\NetteMercure\Bridge\DI\Dependency\BroadcastersDefiner;
use Raneomik\NetteMercure\Bridge\DI\Dependency\MercureHubsDefiner;
use Raneomik\NetteMercure\Bridge\DI\Dependency\SubscribersDefiner;
use Raneomik\NetteMercure\Bridge\Latte\MercureExtension as LatteMercureExtension;
use Raneomik\NetteMercure\Bridge\Tracy\MercurePanel;
use Raneomik\NetteMercure\Bridge\Utils\BroadcastersLoader;
use Raneomik\NetteMercure\Bridge\Utils\ConfiguredData;
use Raneomik\NetteMercure\Bridge\Utils\ConfiguredDataRegistry;
use Symfony\Component\Mercure\Jwt\LcobucciFactory;

final class MercureExtension extends Nette\DI\CompilerExtension
{
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
                'jwt' => Expect::structure([
                    'secret' => Expect::string()->required()->dynamic(),
                    'publish' => Expect::arrayOf('string')->default(['*'])->dynamic(),
                    'subscribe' => Expect::arrayOf('string')->default(['*'])->dynamic(),
                    'algorithm' => Expect::string('hmac.sha256'),
                    'factory' => Expect::string(LcobucciFactory::class),
                    'lifetime' => Expect::int()->dynamic(),
                ])->required(),
                'disableCookie' => Expect::bool(false),
                'debugger' => Expect::bool('%debugMode%'),
                //                'autowired' => Expect::bool(),
            ]),
        )->before(static fn ($val): mixed => \is_array(reset($val)) || null === reset($val)
            ? $val
            : [
                'default' => $val,
            ]);
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

    public function beforeCompile(): void
    {
        $broadcastersLoader = new BroadcastersDefiner($this);

        $builder = $this->getContainerBuilder();

        /** @var false|ServiceDefinition $latteDefinition */
        $latteDefinition = $builder->hasDefinition('latte.latteFactory')
            // @phpstan-ignore-next-line
            ? $builder->getDefinition('latte.latteFactory')->getResultDefinition()
            : false;

        $configDefinitions = [];
        $broadcasterDefinitions = [];
        foreach ((array) $this->getConfig() as $hubName => $config) {
            $configDefinitions[$hubName] = new Statement(
                ConfiguredData::class,
                [
                    $hubName,
                    $config->url,
                    $config->jwt->subscribe ?? [],
                    $config->jwt->publish ?? [],
                    $config->jwt->noCookie ?? false,
                ]
            );

            $broadcasterDefinitions[$hubName] = $broadcastersLoader->broadcasterDefinition($config, $hubName, $latteDefinition);
        }

        $builder->addDefinition($this->prefix('hubsConfiguration'))
            ->setType(ConfiguredDataRegistry::class)
            ->setFactory(ConfiguredDataRegistry::class, [
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
                        '@'.$this->prefix('jwtProvider'),
                        new Statement(BroadcastersLoader::class, [
                            $builder::literal('fn() => $this->getService(?)', [
                                $this->prefix('broadcasters'),
                            ]),
                        ]),
                        '@'.$this->prefix('hubsConfiguration'),
                    ]),
                ])
            ;
        }

        if ($builder->hasDefinition('tracy.bar')) {
            $panelDef = $builder->addDefinition($this->prefix('tracy.panel'))
                ->setFactory(MercurePanel::class, [
                    new Statement(BroadcastersLoader::class, [
                        $builder::literal('fn() => $this->getService(?)', [
                            $this->prefix('broadcasters'),
                        ]),
                    ]),
                    $this->hotReloadUrl,
                ])
                ->setAutowired(false)
            ;

            // @phpstan-ignore-next-line
            $builder->getDefinition('tracy.bar')
                ->addSetup('?->addPanel(?, ?)', [
                    '@self',
                    $panelDef,
                    'mercure',
                ])
            ;
        }
    }

    public function getDebugMode(): bool
    {
        return $this->debugMode;
    }
}
