<?php

declare(strict_types=1);

namespace Tests\Unit\Raneomik\NetteMercure\Bridge\Tracy\Model;

require dirname(__DIR__, 4) . '/bootstrap.php';

use Raneomik\NetteMercure\Bridge\Tracy\Model\HubData;
use Raneomik\NetteMercure\Core\Broadcasters;
use Raneomik\NetteMercure\Core\PlainBroadcaster;
use Symfony\Component\Mercure\Jwt\StaticTokenProvider;
use Symfony\Component\Mercure\MockHub;
use Symfony\Component\Mercure\Update;
use Tester\Assert;
use Tester\TestCase;
use Tests\Fixtures\Dummies\DummyBroadcastContext;

class HubDataTest extends TestCase
{
    /**
     * @testCase
     */
    public function testMinimalistOptions(): void
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

		$hubData = new HubData($broadcasters);

		Assert::type(HubData::class, $hubData);
	}
}

new HubDataTest()->run();

