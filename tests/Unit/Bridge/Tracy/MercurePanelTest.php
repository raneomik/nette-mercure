<?php

declare(strict_types=1);

namespace Tests\Unit\Raneomik\NetteMercure\Bridge\Tracy;

require \dirname(__DIR__, 3) . '/bootstrap.php';

use Raneomik\NetteMercure\Bridge\DI\Config\Configuration;
use Raneomik\NetteMercure\Bridge\DI\Config\ConfigurationRegistry;
use Raneomik\NetteMercure\Bridge\Tracy\MercurePanel;
use Raneomik\NetteMercure\Bridge\Utils\BroadcastersLoader;
use Raneomik\NetteMercure\Core\Publish\Broadcasters;
use Raneomik\NetteMercure\Core\Publish\PlainBroadcaster;
use Raneomik\NetteMercure\Core\Publish\Tracy\TraceableBroadcaster;
use Raneomik\NetteMercure\Core\Subscribe\JWTProvider;
use Symfony\Component\Mercure\HubRegistry;
use Tester\Assert;
use Tester\TestCase;
use Tests\Fixtures\Dummies\Core\MockHubFactory;

/**
 * @testCase
 */
final class MercurePanelTest extends TestCase
{
    protected function tearDown(): void
    {
        @unlink(__DIR__ . '/test.js');
    }

    public function testPanelComponents(): void
    {
        $testFile = 'test.js';
        Assert::false(file_exists($testFile));

        $broadcaster = new PlainBroadcaster(
            $defaultHub = MockHubFactory::create('http://hub.example.com'),
        );
        $nullFactoryBroadcaster = new PlainBroadcaster(
            $nullFactoryHub = MockHubFactory::create('http://nullhub.example.com', withoutJWTFactory: true),
        );
        $broadcasters = new Broadcasters([
            'test' => $broadcaster = new TraceableBroadcaster($broadcaster),
            'nullFactory' => $nullFactoryBroadcaster,
        ]);

        $panel = new MercurePanel(
            ...$params = [
                new BroadcastersLoader(static fn (): Broadcasters => $broadcasters),
                new JWTProvider(
                    new HubRegistry(
                        $defaultHub,
                        [
                            'test' => $defaultHub,
                            'nullFactory' => $nullFactoryHub,
                        ]
                    ),
                ),
                new ConfigurationRegistry([
                    'test' => new Configuration(
                        hubName: 'test',
                        hubUrl: 'http://hub.example.com',
                        publicUrl: 'http://hub.example.com',
                        subscribe: ['*'],
                        publish: ['*'],
                    ),
                    'nullFactory' => new Configuration(
                        hubName: 'test',
                        hubUrl: 'http://nullhub.example.com',
                        publicUrl: 'http://nullhub.example.com',
                        subscribe: ['*'],
                        publish: ['*'],
                    ),
                ]),
                'https://hot-reload.example.com',
                $testFile,
            ]
        );

        Assert::type('string', $panel->getPanel(), 'no messages');

        $broadcaster->broadcast('test', 'Hello, Mercure!');
        Assert::type('string', $panel->getPanel(), 'test traced data rendering');
        Assert::type('string', $panel->getTab());

        $panel = new MercurePanel(...$params);
        Assert::type('string', $panel->getTab(), 'test HR file existence');
        Assert::true(file_exists($testFile));

        // a changed URL rewrites the cached script, encoded for a JS string
        new MercurePanel($params[0], "https://hr.example.com/?topic=a&b='c'", $testFile);
        Assert::contains('meta.content = "https://hr.example.com/?topic=a&b=\'c\'";', (string) file_get_contents($testFile));

        unlink($testFile);
    }
}

(new MercurePanelTest())->run();
