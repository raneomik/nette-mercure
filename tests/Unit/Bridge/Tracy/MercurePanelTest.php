<?php

declare(strict_types=1);

namespace Tests\Unit\Raneomik\NetteMercure\Bridge\Latte;

require \dirname(__DIR__, 3).'/bootstrap.php';

use Raneomik\NetteMercure\Bridge\Tracy\MercurePanel;
use Raneomik\NetteMercure\Bridge\Utils\BroadcastersLoader;
use Raneomik\NetteMercure\Core\Publish\Broadcasters;
use Raneomik\NetteMercure\Core\Publish\PlainBroadcaster;
use Raneomik\NetteMercure\Core\Publish\Tracy\TraceableBroadcaster;
use Symfony\Component\Mercure\Jwt\StaticTokenProvider;
use Symfony\Component\Mercure\MockHub;
use Symfony\Component\Mercure\Update;
use Tester\Assert;
use Tester\TestCase;

final class MercurePanelTest extends TestCase
{
    /**
     * @testCase
     */
    public function testPanelComponents(): void
    {
        $broadcaster = new PlainBroadcaster(
            new MockHub(
                'http://hub.example.com',
                new StaticTokenProvider('!ChangeMe1!'),
                static fn (Update $update): string => $update->getData(),
            ),
        );
        $broadcasters = new Broadcasters([
            'test' => $broadcaster = new TraceableBroadcaster($broadcaster),
        ]);

        $panel = new MercurePanel(
            ...$params = [
                new BroadcastersLoader(static fn (): Broadcasters => $broadcasters),
                'https://hot-reload.example.com',
                $testFile = 'test.js',
            ]
        );

        Assert::type('string', $panel->getPanel(), 'no messages');

        $broadcaster->broadcast('test', 'Hello, Mercure!');
        Assert::type('string', $panel->getPanel(), 'test traced data rendering');
        Assert::type('string', $panel->getTab());

        $panel = new MercurePanel(...$params);
        Assert::type('string', $panel->getTab(), 'test HR file existence');

        unlink($testFile);
    }
}

(new MercurePanelTest())->run();
