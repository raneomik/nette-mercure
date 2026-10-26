<?php

declare(strict_types=1);

namespace Tests\Unit\Raneomik\NetteMercure\Bridge\Tracy;

require \dirname(__DIR__, 3) . '/bootstrap.php';

use Raneomik\NetteMercure\Bridge\DI\Config\Configuration;
use Raneomik\NetteMercure\Bridge\DI\Config\ConfigurationRegistry;
use Raneomik\NetteMercure\Bridge\Tracy\CustomScriptsLoader;
use Raneomik\NetteMercure\Bridge\Tracy\MercurePanel;
use Raneomik\NetteMercure\Bridge\Utils\BroadcastersLoader;
use Raneomik\NetteMercure\Core\Publish\Broadcasters;
use Raneomik\NetteMercure\Core\Publish\PlainBroadcaster;
use Raneomik\NetteMercure\Core\Publish\Tracy\TraceableBroadcaster;
use Tester\Assert;
use Tester\TestCase;
use Tests\Fixtures\Dummies\Core\MockHubFactory;
use Tracy\Helpers;

/**
 * @testCase
 */
final class MercurePanelTest extends TestCase
{
    public function testPanelComponents(): void
    {
        $broadcaster = new PlainBroadcaster(
            MockHubFactory::create('http://hub.example.com'),
        );
        $nullFactoryBroadcaster = new PlainBroadcaster(
            MockHubFactory::create('http://nullhub.example.com', withoutJWTFactory: true),
        );
        $broadcasters = new Broadcasters([
            'test' => $broadcaster = new TraceableBroadcaster($broadcaster),
            'nullFactory' => $nullFactoryBroadcaster,
        ]);

        $configs = new ConfigurationRegistry([
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
        ]);

        $panel = new MercurePanel(
            ...$params = [
                new BroadcastersLoader(static fn(): Broadcasters => $broadcasters),
                $configs,
                new CustomScriptsLoader(
                    'https://hot-reload.example.com',
                ),
            ]
        );

        Assert::type('string', $panel->getPanel(), 'no messages');

        $broadcaster->broadcast('test', 'Hello, Mercure!');
        Assert::type('string', $panel->getPanel(), 'test traced data rendering');
        Assert::type('string', $panel->getTab());

        $panel = new MercurePanel(...$params);
        Assert::type('string', $panel->getTab(), 'test HR file existence');

        // a changed URL rewrites the cached script, encoded for a JS string
        [$loader] = $params;
        $panel = new MercurePanel(
            $loader,
            $configs,
            new CustomScriptsLoader(
                "https://hr.example.com/?topic=a&b='c'",
            ),
        );
        Assert::contains(
            'meta.content = ' . Helpers::jsonEncode("https://hr.example.com/?topic=a&b='c'"),
            $panel->getTab(),
        );
    }
}

(new MercurePanelTest())->run();
