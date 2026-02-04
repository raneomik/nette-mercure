<?php

declare(strict_types=1);

namespace Tests\Unit\Raneomik\NetteMercure\Bridge\Tracy\Model;

require dirname(__DIR__, 4) . '/bootstrap.php';

use Raneomik\NetteMercure\Bridge\Tracy\Model\HubData;
use Raneomik\NetteMercure\Bridge\Tracy\Model\HubDatum;
use Raneomik\NetteMercure\Core\Broadcasters;
use Raneomik\NetteMercure\Core\PlainBroadcaster;
use Raneomik\NetteMercure\Tracy\TraceableBroadcaster;
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
        $broadcasters = new Broadcasters([
            'test' => new PlainBroadcaster(
                new MockHub(
                    'test',
                    new StaticTokenProvider('!ChangeMe1!'),
                    fn(Update $update): string => $update->getData(),
                ),
                new DummyBroadcastContext(),
            ),
        ]);

		$hubData = new HubData($broadcasters);

		Assert::type(HubData::class, $hubData);
	}

    /**
     * @testCase
     */
    public function testRelevantData(): void
	{
		$publishCallback = fn(Update $update): string => $update->getData();
        $plainBro = new PlainBroadcaster(
            new MockHub(
                'test',
                new StaticTokenProvider('!ChangeMe1!'),
                $publishCallback,
            ),
            new DummyBroadcastContext(),
        );
        $anotherBro = new PlainBroadcaster(
            new MockHub(
                'test2',
                new StaticTokenProvider('!ChangeMe2!'),
                $publishCallback,
            ),
            new DummyBroadcastContext(),
        );

        $broadcasters = new Broadcasters([
            'test' => new TraceableBroadcaster($plainBro),
            'test2' => new TraceableBroadcaster($anotherBro),
        ]);

        $broadcasters->broadcast('test', 'test');
        $broadcasters->broadcast('test', 'test', [
            'hub' => 'test2',
        ]);

        $hubData = new HubData($broadcasters);

        foreach ($hubData as $hubDatum) {
            Assert::type(HubDatum::class, $hubDatum);
            Assert::notEqual(0.0, $hubDatum->duration);
            Assert::notEqual(0.0, $hubDatum->memory);
            Assert::notEqual([], $hubDatum->broadcastData);
        }

        /** @var HubDatum $datum1 */
        $datum1 = $hubData['test'];
        /** @var HubDatum $datum2 */
        $datum2 = $hubData['test'];

        Assert::equal(1, $datum1->messageCount());
        Assert::equal(1, $datum2->messageCount());
        Assert::notEqual(0.0, $hubData->totalDuration);
        Assert::notEqual(0.0, $hubData->totalMemory);
	}
}

(new HubDataTest())->run();

