<?php

declare(strict_types=1);

namespace Tests\Unit\Raneomik\NetteMercure\Bridge\DI\Config;

require \dirname(__DIR__, 4) . '/bootstrap.php';

use Raneomik\NetteMercure\Bridge\DI\Config\Configuration;
use Raneomik\NetteMercure\Bridge\DI\Config\ConfigurationRegistry;
use Tester\Assert;
use Tester\TestCase;

/**
 * @testCase
 */
final class ConfigurationRegistryTest extends TestCase
{
    private ConfigurationRegistry $registry;

    protected function setUp(): void
    {
        $this->registry = new ConfigurationRegistry([
            'hub-a' => $this->config('hub-a', 'https://a.example.com'),
            'hub-b' => $this->config('hub-b', 'https://b.example.com'),
        ]);
    }

    public function testGetDefaultReturnsFirst(): void
    {
        Assert::same('hub-a', $this->registry->getConfiguration()->hubName);
    }

    public function testGetByNameReturnsMatch(): void
    {
        Assert::same('hub-b', $this->registry->getConfiguration('hub-b')->hubName);
    }

    public function testGetUnknownNameThrows(): void
    {
        Assert::exception(
            fn (): Configuration => $this->registry->getConfiguration('unknown'),
            \InvalidArgumentException::class,
            '~unknown~i',
        );
    }

    private function config(string $name, string $url): Configuration
    {
        return new Configuration(
            hubName: $name,
            hubUrl: $url,
            publicUrl: $url,
            subscribe: ['*'],
            publish: ['*'],
        );
    }
}

(new ConfigurationRegistryTest())->run();
