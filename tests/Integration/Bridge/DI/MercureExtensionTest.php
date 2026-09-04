<?php

declare(strict_types=1);

namespace Tests\Integration\Raneomik\NetteMercure\Bridge\DI;

require \dirname(__DIR__, 3).'/bootstrap.php';

use Latte\Engine;
use Latte\Extension;
use Nette\Bootstrap\Configurator;
use Nette\Bridges\ApplicationLatte\LatteFactory;
use Nette\DI\Compiler;
use Nette\DI\Config\Loader;
use Nette\DI\Container;
use Nette\DI\MissingServiceException;
use Raneomik\NetteMercure\Bridge\DI\Config\ConfiguredDataRegistry;
use Raneomik\NetteMercure\Bridge\DI\MercureExtension;
use Raneomik\NetteMercure\Bridge\Latte\MercureExtension as LatteMercureExtension;
use Raneomik\NetteMercure\Bridge\Tracy\MercurePanel;
use Raneomik\NetteMercure\BroadcasterInterface;
use Raneomik\NetteMercure\Core\Discovery;
use Raneomik\NetteMercure\Core\Publish\Broadcasters;
use Raneomik\NetteMercure\Core\Publish\Latte\TemplatePathResolver;
use Raneomik\NetteMercure\Core\Publish\Latte\TemplatingBroadcaster;
use Raneomik\NetteMercure\Core\Publish\PlainBroadcaster;
use Raneomik\NetteMercure\Core\Publish\Tracy\TraceableBroadcaster;
use Raneomik\NetteMercure\Core\Subscribe\Subscriber;
use Symfony\Component\Mercure\FrankenPhpHub;
use Symfony\Component\Mercure\Hub;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\HubRegistry;
use Symfony\Component\Mercure\Jwt\TokenFactoryInterface;
use Symfony\Component\Mercure\Jwt\TokenProviderInterface;
use Symfony\Component\WebLink\HttpHeaderSerializer;
use Tester\Assert;
use Tester\FileMock;
use Tester\Helpers;
use Tester\TestCase;
use Tests\Fixtures\Dummies\Core\DummyJwtFactory;
use Tracy\Bar;

/**
 * @testCase
 */
final class MercureExtensionTest extends TestCase
{
    protected Configurator $configurator;

    protected function setUp(): void
    {
        mkdir($this->tmpDir(), recursive: true);

        $this->configurator = (new Configurator())
            ->setTempDirectory($this->tmpDir())
            ->addConfig(\dirname(__DIR__, 3).'/fixtures/config/test.neon')
        ;
    }

    protected function tearDown(): void
    {
        Helpers::purge($this->tmpDir());
        rmdir($this->tmpDir());
    }

    public function testPlainSimpleCompilation(): void
    {
        $loader = new Loader();
        $config = $loader->load(FileMock::create('
        mercure:
            url: /.well-known/mercure
            jwt:
                secret: jwt-secret
        ', 'neon'));

        $compiler = new Compiler();
        $compiler->addExtension('mercure', new MercureExtension(false));
        eval($compiler->addConfig($config)->setClassName($containerName = 'Container1')->compile());

        /**
         * @var Container
         *
         * @phpstan-ignore-next-line
         */
        $container = new $containerName();
        $container->initialize();

        $toTest = [
            'mercure.sf.hub.default' => HubInterface::class,
            'mercure.broadcaster.default.plain' => PlainBroadcaster::class,
            'mercure.broadcasters' => Broadcasters::class,
        ];

        foreach ($toTest as $serviceAlias => $expectedType) {
            $service = $container->getService($serviceAlias);
            Assert::type($expectedType, $service);
        }

        Assert::exception(
            static fn (): object => $container->getByType(LatteFactory::class),
            MissingServiceException::class,
            \sprintf('~Service of type %s not found~', preg_quote(LatteFactory::class)),
        );

        Assert::exception(
            static fn (): object => $container->getByType(TemplatingBroadcaster::class),
            MissingServiceException::class,
            \sprintf('~Service of type %s not found~', preg_quote(TemplatingBroadcaster::class)),
        );

        Assert::exception(
            static fn () => $container->getService('mercure.broadcaster.default.latte'),
            MissingServiceException::class,
            "Service 'mercure.broadcaster.default.latte' not found.",
        );
    }

    public function testBasicCompilation(): void
    {
        $this->configurator->onCompile[] = static function ($configurator, $compiler): void {
            $compiler->addExtension('mercure', new MercureExtension(false));
        };

        $this->configurator->addConfig(FileMock::create('
        mercure:
            url: /.well-known/mercure
            jwt:
                secret: jwt-secret

        ', 'neon'));

        $container = $this->configurator->createContainer();

        $service = $container->getService('mercure.subscriber');
        Assert::type(Subscriber::class, $service);
        $service = $container->getService('mercure.discovery');
        Assert::type(Discovery::class, $service);
        $service = $container->getByType(Discovery::class);
        Assert::type(Discovery::class, $service);

        $service = $container->getService('mercure.broadcasters');
        Assert::type(Broadcasters::class, $service);

        $service = $container->getByType(BroadcasterInterface::class);
        Assert::type(Broadcasters::class, $service);

        $service = $container->getByType(BroadcasterInterface::class);
        Assert::type(Broadcasters::class, $service);

        $toTest = [
            'mercure.sf.hub.default' => HubInterface::class,
            'mercure.token.factory.default' => TokenFactoryInterface::class,
            'mercure.token.provider.default' => TokenProviderInterface::class,
            'mercure.symfony.links.headerSerializer' => HttpHeaderSerializer::class,
            'mercure.symfony.hub.registry' => HubRegistry::class,
            'mercure.broadcaster.default.plain' => PlainBroadcaster::class,
            'mercure.broadcaster.default.latte' => TemplatingBroadcaster::class,
        ];

        foreach ($toTest as $serviceAlias => $expectedType) {
            $service = $container->getService($serviceAlias);
            Assert::type($expectedType, $service);
        }

        /** @var Engine $engine */
        $engine = $container->getService('latte.latteFactory')
            ->create()
        ;
        Assert::contains(
            LatteMercureExtension::class,
            array_map(static fn (Extension $ext): string => $ext::class, $engine->getExtensions()),
        );

        Assert::exception(
            static fn (): object => $container->getService('mercure.broadcaster.default.traceable'),
            MissingServiceException::class,
            "Service 'mercure.broadcaster.default.traceable' not found.",
        );

        Assert::exception(
            static fn (): object => $container->getByType(FrankenPhpHub::class),
            MissingServiceException::class,
            \sprintf('~Service of type %s not found~', preg_quote(FrankenPhpHub::class)),
        );

        foreach ($toTest as $notAutowired) {
            Assert::exception(
                static fn (): object => $container->getByType($notAutowired),
                MissingServiceException::class,
                \sprintf('~Service of type %s is not autowired or is missing~', preg_quote($notAutowired)),
            );
        }
    }

    public function testSimulatedFrankenPhpCompilation(): void
    {
        putenv('FRANKENPHP_CONFIG=1'); // simulate FrankenPHP environment

        $this->configurator->setDebugMode(false);
        $this->configurator->onCompile[] = static function ($configurator, $compiler): void {
            $compiler->addExtension('mercure', new MercureExtension(false, 'test-url'));
        };

        $this->configurator->addConfig(FileMock::create('
        mercure:
            url: /.well-known/mercure
            jwt:
                secret: jwt-secret
        ', 'neon'));

        $container = $this->configurator->createContainer();
        $toTest = [
            'mercure.sf.hub.default' => FrankenPhpHub::class,
            'mercure.broadcaster.default.plain' => PlainBroadcaster::class,
            'mercure.broadcasters' => Broadcasters::class,
        ];

        foreach ($toTest as $serviceAlias => $expectedType) {
            $service = $container->getService($serviceAlias);
            Assert::type($expectedType, $service);
        }

        Assert::exception(
            static fn (): object => $container->getByType(Hub::class),
            MissingServiceException::class,
            \sprintf('~Service of type %s not found~', preg_quote(Hub::class)),
        );

        Assert::exception(
            static fn (): object => $container->getByType(TraceableBroadcaster::class),
            MissingServiceException::class,
            \sprintf('~Service of type %s not found~', preg_quote(TraceableBroadcaster::class)),
        );
        Assert::exception(
            static fn (): object => $container->getService('mercure.broadcaster.default.traceable'),
            MissingServiceException::class,
            "Service 'mercure.broadcaster.default.traceable' not found.",
        );
        Assert::exception(
            static fn (): object => $container->getByType(HubInterface::class),
            MissingServiceException::class,
            \sprintf('~Service of type %s is not autowired or is missing~', preg_quote(HubInterface::class)),
        );

        putenv('FRANKENPHP_CONFIG=0');
    }

    public function testMultiCompilationWithDebug(): void
    {
        $this->configurator
            ->setDebugMode(true)
            ->enableTracy($this->tmpDir())
        ;
        $this->configurator->onCompile[] = static function ($configurator, $compiler): void {
            $compiler->addExtension('mercure', new MercureExtension(true));
        };

        $this->configurator->addConfig(FileMock::create('
        mercure:
            hub1:
                url: /.well-known/mercure/hub1
                jwt:
                    secret: jwt-secret
            hub2:
                url: /.well-known/mercure/hub2
                jwt:
                    secret: jwt-secret

        ', 'neon'));

        $container = $this->configurator->createContainer();

        /** @var Bar $tracyBar */
        $tracyBar = $container->getService('tracy.bar');
        Assert::type(MercurePanel::class, $tracyBar->getPanel('mercure'));

        $toTest = [
            'mercure.sf.hub.hub1' => HubInterface::class,
            'mercure.sf.hub.hub2' => Hub::class,
            'mercure.broadcaster.hub1.plain' => PlainBroadcaster::class,
            'mercure.broadcaster.hub2.plain' => PlainBroadcaster::class,
            'mercure.broadcaster.hub1.latte' => TemplatingBroadcaster::class,
            'mercure.broadcaster.hub2.latte' => TemplatingBroadcaster::class,
            'mercure.broadcaster.hub1.traceable' => TraceableBroadcaster::class,
            'mercure.broadcaster.hub2.traceable' => TraceableBroadcaster::class,
            'mercure.tracy.panel' => MercurePanel::class,
            'latte.templatePathResolver' => TemplatePathResolver::class,
            'mercure.hubsConfiguration' => ConfiguredDataRegistry::class,
        ];

        foreach ($toTest as $serviceAlias => $expectedType) {
            $service = $container->getService($serviceAlias);
            Assert::type($expectedType, $service);
        }

        foreach (array_unique(array_values($toTest)) as $notAutowired) {
            Assert::exception(
                static fn (): object => $container->getByType($notAutowired),
                MissingServiceException::class,
                \sprintf('~Service of type %s is not autowired or is missing~', preg_quote($notAutowired)),
            );
        }
    }

    public function testCompilationWithCustomJwtImplementation(): void
    {
        $dummyClass = DummyJwtFactory::class;

        $this->configurator->onCompile[] = static function ($configurator, $compiler): void {
            $compiler->addExtension('mercure', new MercureExtension(false));
        };

        $this->configurator->addConfig(FileMock::create("
        mercure:
            url: /.well-known/mercure
            jwt:
                secret: jwt-secret
                factory: {$dummyClass}
        ", 'neon'));
        $container = $this->configurator->createContainer();

        $toTest = [
            'mercure.sf.hub.default' => HubInterface::class,
            'mercure.broadcaster.default.plain' => PlainBroadcaster::class,
            'mercure.broadcasters' => Broadcasters::class,
        ];

        foreach ($toTest as $serviceAlias => $expectedType) {
            $service = $container->getService($serviceAlias);
            Assert::type($expectedType, $service);
        }

        /** @var Bar $tracyBar */
        $tracyBar = $container->getService('tracy.bar');
        Assert::falsey($tracyBar->getPanel('mercure'));
    }

    private function tmpDir(): string
    {
        return \sprintf('%s/var/log.test.%s', \dirname(__DIR__, 4), getmypid());
    }
}

(new MercureExtensionTest())->run();
