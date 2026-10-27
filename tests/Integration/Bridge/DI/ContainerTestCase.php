<?php

declare(strict_types=1);

namespace Tests\Integration\Raneomik\NetteMercure\Bridge\DI;

use Nette\Bootstrap\Configurator;
use Nette\DI\Container;
use Nette\DI\MissingServiceException;
use Raneomik\NetteMercure\Bridge\DI\MercureExtension;
use Tester\Assert;
use Tester\FileMock;
use Tester\Helpers;
use Tester\TestCase;

/**
 * Compiles a container with the Mercure extension from an inline NEON configuration.
 */
abstract class ContainerTestCase extends TestCase
{
    protected function setUp(): void
    {
        mkdir($this->tmpDir(), recursive: true);

    }

    protected function tearDown(): void
    {
        Helpers::purge($this->tmpDir());
        rmdir($this->tmpDir());
    }

    protected function createContainer(string $neon, bool $debug = false): Container
    {
        $configurator = (new Configurator())
            ->setTempDirectory($this->tmpDir())
            ->addConfig(\dirname(__DIR__, 3) . '/fixtures/config/test.neon')
        ;

        if ($debug) {
            $configurator
                ->setDebugMode(true)
                ->enableTracy($this->tmpDir())
            ;
        }

        $configurator->onCompile[] = static function ($configurator, $compiler) use ($debug): void {
            $compiler->addExtension('mercure', new MercureExtension($debug));
        };

        $configurator->addConfig(FileMock::create($neon, 'neon'));

        return $configurator->createContainer();
    }

    /**
     * @param class-string[] $types
     */
    protected static function assertNotAutowired(Container $container, array $types): void
    {
        foreach ($types as $type) {
            Assert::exception(
                static fn (): object => $container->getByType($type),
                MissingServiceException::class,
                \sprintf('~Service of type %s is not autowired or is missing~', preg_quote($type)),
            );
        }
    }

    /**
     * @param class-string[] $types
     */
    protected static function assertTypeNotFound(Container $container, array $types): void
    {
        foreach ($types as $type) {
            Assert::exception(
                static fn (): object => $container->getByType($type),
                MissingServiceException::class,
                \sprintf('~Service of type %s not found~', preg_quote($type)),
            );
        }
    }

    protected function tmpDir(): string
    {
        return \sprintf('%s/var/log.test.%s', \dirname(__DIR__, 4), getmypid());
    }
}
