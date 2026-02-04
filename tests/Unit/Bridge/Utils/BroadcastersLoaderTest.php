<?php

declare(strict_types=1);

namespace Tests\Unit\Raneomik\NetteMercure\Bridge\Utils;

require dirname(__DIR__, 3) . '/bootstrap.php';

use Raneomik\NetteMercure\Bridge\Utils\BroadcastersLoader;
use Raneomik\NetteMercure\Core\Broadcasters;
use Raneomik\NetteMercure\Core\PlainBroadcaster;
use Symfony\Component\Mercure\Jwt\StaticTokenProvider;
use Symfony\Component\Mercure\MockHub;
use Symfony\Component\Mercure\Update;
use Tester\Assert;
use Tester\TestCase;
use Tests\Fixtures\Dummies\DummyBroadcastContext;

class BroadcastersLoaderTest extends TestCase
{
    /**
     * @testCase
     */
    public function testLoad(): void
	{
		$publishcallback = fn(Update $update): string => $update->getData();

        $broadcasters = new Broadcasters([
            'test' => new PlainBroadcaster(
                new MockHub(
                    'test',
                    new StaticTokenProvider('!ChangeMe1!'),
                    $publishcallback,
                ),
                new DummyBroadcastContext(),
            ),
        ]);

		$loader = new BroadcastersLoader(fn(): Broadcasters => $broadcasters);

		Assert::type(Broadcasters::class, $loader());
	}
}

(new BroadcastersLoaderTest())->run();

