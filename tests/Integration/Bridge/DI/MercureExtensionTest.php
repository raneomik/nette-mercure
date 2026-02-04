<?php

declare(strict_types=1);

namespace Tests\Integration\Raneomik\NetteMercure\Bridge\DI;

require dirname(__DIR__, 3) . '/bootstrap.php';

use Nette\Bootstrap\Configurator;
use Nette\DI\Compiler;
use Nette\DI\Config\Loader;
use Nette\DI\MissingServiceException;
use Raneomik\NetteMercure\Bridge\DI\MercureExtension;
use Raneomik\NetteMercure\BroadcasterInterface;
use Raneomik\NetteMercure\Core\Broadcasters;
use Raneomik\NetteMercure\Core\PlainBroadcaster;
use Raneomik\NetteMercure\Latte\TemplatingBroadcaster;
use Raneomik\NetteMercure\Tracy\TraceableBroadcaster;
use Symfony\Component\Mercure\FrankenPhpHub;
use Symfony\Component\Mercure\Hub;
use Symfony\Component\Mercure\HubInterface;
use Tester\Assert;
use Tester\FileMock;
use Tester\Helpers;
use Tester\TestCase;
use Tests\Fixtures\Dummies\DummyJwtFactory;

class MercureExtensionTest extends TestCase
{
    protected Configurator $configurator;

    protected function setUp(): void
    {
        mkdir(dirname(__DIR__, 4) . '/var/log.test', recursive: true);

        $this->configurator = (new Configurator())
            ->setTempDirectory(dirname(__DIR__, 4) . '/var/temp.test')
            ->addConfig(dirname(__DIR__, 3) . '/fixtures/config/test.neon');
    }

    protected function tearDown(): void
    {
        Helpers::purge(dirname(__DIR__, 4) . '/var');
        rmdir(dirname(__DIR__, 4) . '/var');
    }

	/**
	 * @testCase
	 */
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

        /** @phpstan-ignore-next-line */
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
            fn() => $container->getService('mercure.broadcaster.default.latte'),
            MissingServiceException::class,
            "Service 'mercure.broadcaster.default.latte' not found.",
        );
    }

	/**
	 * @testCase
	 */
	public function testBasicCompilation(): void
	{
        $this->configurator->onCompile[] = function ($configurator, $compiler): void {
            $compiler->addExtension('mercure', new MercureExtension(false));
        };

        $this->configurator->addConfig(FileMock::create('
        mercure:
            url: /.well-known/mercure
            jwt:
                secret: jwt-secret

        ', 'neon'));

		$container = $this->configurator->createContainer();

		$toTest = [
		    'mercure.sf.hub.default' => HubInterface::class,
		    'mercure.broadcaster.default.plain' => PlainBroadcaster::class,
		    'mercure.broadcaster.default.latte' => TemplatingBroadcaster::class,
		    'mercure.broadcasters' => Broadcasters::class,
		];

		foreach ($toTest as $serviceAlias => $expectedType) {
			$service = $container->getService($serviceAlias);
			Assert::type($expectedType, $service);
		}

		Assert::exception(
		    fn(): object => $container->getService('mercure.broadcaster.default.traceable'),
		    MissingServiceException::class,
		    "Service 'mercure.broadcaster.default.traceable' not found.",
		);

        Assert::exception(
            fn(): object => $container->getByType(FrankenPhpHub::class),
            MissingServiceException::class,
            "~Service of type Symfony\\\Component\\\Mercure\\\FrankenPhpHub not found~",
        );
	}

    /**
     * @testCase
     */
    public function testFrankenPhpCompilation(): void
    {
        putenv('FRANKENPHP_CONFIG=1'); //simulate FrankenPHP environment

        $this->configurator->setDebugMode(false);
        $this->configurator->onCompile[] = function ($configurator, $compiler): void {
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
            fn(): object => $container->getByType(Hub::class),
            MissingServiceException::class,
            "~Service of type Symfony\\\Component\\\Mercure\\\Hub not found~",
        );

        Assert::exception(
            fn(): object => $container->getService('mercure.broadcaster.default.traceable'),
            MissingServiceException::class,
            "Service 'mercure.broadcaster.default.traceable' not found.",
        );

        putenv('FRANKENPHP_CONFIG=0');
    }

    /**
     * @testCase
     */
    public function testMultiCompilation(): void
    {
        $this->configurator
            ->setDebugMode(true)
            ->enableTracy(dirname(__DIR__, 4) . '/var/log.test')
        ;
        $this->configurator->onCompile[] = function ($configurator, $compiler): void {
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

        $toTest = [
            'mercure.sf.hub.hub1' => HubInterface::class,
            'mercure.sf.hub.hub2' => Hub::class,
            'mercure.broadcaster.hub1.plain' => PlainBroadcaster::class,
            'mercure.broadcaster.hub2.plain' => PlainBroadcaster::class,
            'mercure.broadcaster.hub1.traceable' => TraceableBroadcaster::class,
            'mercure.broadcaster.hub2.traceable' => TraceableBroadcaster::class,
            'mercure.broadcasters' => Broadcasters::class,
        ];

        foreach ($toTest as $serviceAlias => $expectedType) {
            $service = $container->getService($serviceAlias);
            Assert::type($expectedType, $service);
        }

        $service = $container->getByType(BroadcasterInterface::class);
        Assert::type(Broadcasters::class, $service);
    }

	/**
	 * @testCase
	 */
	public function testCompilationWithCustomJwtImplementation(): void
	{
		$dummyClass = DummyJwtFactory::class;

        $this->configurator->onCompile[] = function ($configurator, $compiler): void {
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
	}
}

(new MercureExtensionTest())->run();
