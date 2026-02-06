<?php

declare(strict_types=1);

namespace Tests\Unit\Raneomik\NetteMercure\Bridge\Latte;

require dirname(__DIR__, 3) . '/bootstrap.php';

use Raneomik\NetteMercure\Bridge\Tracy\MercurePanel;
use Raneomik\NetteMercure\Bridge\Utils\BroadcastersLoader;
use Raneomik\NetteMercure\Core\Broadcasters;
use Raneomik\NetteMercure\Core\PlainBroadcaster;
use Raneomik\NetteMercure\Tracy\TraceableBroadcaster;
use Symfony\Component\Mercure\Jwt\StaticTokenProvider;
use Symfony\Component\Mercure\MockHub;
use Symfony\Component\Mercure\Update;
use Tester\Assert;
use Tester\TestCase;
use Tests\Fixtures\Dummies\DummyBroadcastContext;

class MercurePanelTest extends TestCase
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
                fn(Update $update): string => $update->getData(),
            ),
            new DummyBroadcastContext(),
        );
        $broadcasters = new Broadcasters([
            'test' => $broadcaster = new TraceableBroadcaster($broadcaster),
        ]);

		$panel = new MercurePanel(
		    new BroadcastersLoader(fn(): Broadcasters => $broadcasters),
		    'https://hot-reload.example.com',
		);

        Assert::type('string', $panel->getPanel(), 'no messages');

        $broadcaster->broadcast('test', 'Hello, Mercure!');
		Assert::type('string', $panel->getPanel());
		Assert::type('string', $panel->getTab());
	}
}

(new MercurePanelTest())->run();

