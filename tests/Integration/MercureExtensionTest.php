<?php

declare(strict_types=1);

namespace Tests\Integration;

require __DIR__ . '/../bootstrap.php';

use Nette\DI\Compiler;
use Nette\DI\Config\Loader;
use Nette\DI\MissingServiceException;
use Nette\Mercure\Bridge\DI\MercureExtension;
use Nette\Mercure\BroadcasterInterface;
use Nette\Mercure\Core\Broadcasters;
use Nette\Mercure\Core\PlainBroadcaster;
use Nette\Mercure\Tracy\TraceableBroadcaster;
use Symfony\Component\Mercure\HubInterface;
use Tester\Assert;
use Tester\FileMock;
use Tester\TestCase;

class MercureExtensionTest extends TestCase
{
	public function testBasicCompilation(): void
	{
		$loader = new Loader;
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
		$container = new $containerName;
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
			fn() => $container->getService('mercure.broadcaster.default.traceable'),
			MissingServiceException::class,
			"Service 'mercure.broadcaster.default.traceable' not found.",
		);
	}

	public function testMultiCompilation(): void
	{
		$loader = new Loader;
		$config = $loader->load(FileMock::create('
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

		$compiler = new Compiler();
		$compiler->addExtension('mercure', new MercureExtension(true));
		eval($compiler->addConfig($config)->setClassName($containerName = 'Container2')->compile());

		/** @phpstan-ignore-next-line */
		$container = new $containerName;
		$container->initialize();

		$toTest = [
			'mercure.sf.hub.hub1' => HubInterface::class,
			'mercure.sf.hub.hub2' => HubInterface::class,
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
}

(new MercureExtensionTest)->run();
