<?php

declare(strict_types=1);
use Nette\Application\Application;
use Tests\Fixtures\Dummies\App\Bootstrap;

// Load the Composer autoloader
if (@! include dirname(__DIR__, 5).'/vendor/autoload.php') {
    exit('Install Nette using `composer update`');
}

// Initialize the application environment
$bootstrap = new Bootstrap();

// Create the Dependency Injection container
$container = $bootstrap->bootWebApplication();

// Start the application and handle the incoming request
$application = $container->getByType(Application::class);
$application->run();
