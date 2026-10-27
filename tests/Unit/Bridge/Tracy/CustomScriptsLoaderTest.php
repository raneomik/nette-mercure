<?php

declare(strict_types=1);

namespace Tests\Unit\Raneomik\NetteMercure\Bridge\Tracy;

require \dirname(__DIR__, 3) . '/bootstrap.php';

use Raneomik\NetteMercure\Bridge\Tracy\CustomScriptsLoader;
use Raneomik\NetteMercure\Core\Publish\Tracy\TraceableBroadcaster;
use Tester\Assert;
use Tester\TestCase;

/**
 * @testCase
 */
final class CustomScriptsLoaderTest extends TestCase
{
    private const array HUBS = [
        [
            'name' => 'one',
            'url' => 'http://hub.example.com/.well-known/mercure',
            'isLegacy' => false,
        ],
    ];

    public function testNothingToLoadWithoutHotReloadNorLiveHubs(): void
    {
        Assert::same([], (new CustomScriptsLoader())->scripts([], 'icon'));
    }

    public function testHotReloadAlone(): void
    {
        $scripts = (new CustomScriptsLoader('https://hot-reload.example.com'))->scripts([], 'icon');

        Assert::count(1, $scripts);
        Assert::contains('frankenphp-hot-reload', $scripts[0]);
    }

    public function testThePageHookIsInstalledBeforeThePanelListensToIt(): void
    {
        $scripts = (new CustomScriptsLoader())->scripts(self::HUBS, 'icon');

        Assert::count(2, $scripts);
        Assert::contains('window.__mercureDebug =', $scripts[0], 'the hook comes first');
        Assert::contains('window.tracyMercureEventLoader =', $scripts[1], 'then the panel, which subscribes to it at start');
        Assert::contains(TraceableBroadcaster::DEBUG_TOPIC, $scripts[0]);
        Assert::contains(TraceableBroadcaster::DEBUG_TOPIC, $scripts[1]);
        Assert::contains(TraceableBroadcaster::DEBUG_EVENT, $scripts[1]);
    }

    public function testHotReloadComesFirst(): void
    {
        $scripts = (new CustomScriptsLoader('https://hot-reload.example.com'))
            ->scripts(self::HUBS, 'icon');

        Assert::count(3, $scripts);
        Assert::contains('frankenphp-hot-reload', $scripts[0]);
        Assert::contains('window.__mercureDebug =', $scripts[1]);
        Assert::contains('window.tracyMercureEventLoader =', $scripts[2]);
    }
}

(new CustomScriptsLoaderTest())->run();
