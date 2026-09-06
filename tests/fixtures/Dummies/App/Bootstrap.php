<?php

declare(strict_types=1);

namespace Tests\Fixtures\Dummies\App;

use Nette;
use Nette\Application\Routers\SimpleRouter;
use Nette\Bootstrap\Configurator;
use Raneomik\NetteMercure\Bridge\DI\MercureExtension;

class Bootstrap
{
    public static string $config = 'functional.test.neon';

    private readonly Configurator $configurator;

    public function __construct(
    ) {
        $this->configurator = new Configurator();

        $this->configurator->setTempDirectory(self::varDir().'/temp');
    }

    public static function varDir(): string
    {
        return __DIR__.'/var';
    }

    public function bootWebApplication(): Nette\DI\Container
    {
        $this->initializeEnvironment();
        $this->setupContainer();

        return $this->configurator->createContainer();
    }

    public function initializeEnvironment(): void
    {
        $this->configurator->createRobotLoader()
            ->addDirectory(__DIR__)
            ->register()
        ;
    }

    private function setupContainer(): void
    {
        $this->configurator->onCompile[] = static function (
            Configurator $configurator,
            Nette\DI\Compiler $compiler,
        ): void {
            $compiler->addExtension('mercure', new MercureExtension(true));
        };

        $this->configurator->addConfig(__DIR__.\DIRECTORY_SEPARATOR.self::$config);

        $this->configurator->addServices(
            [
                'router' => new SimpleRouter('Home:default'),
            ]
        );
    }
}
