<?php

declare(strict_types=1);

namespace Tests\Unit\Raneomik\NetteMercure\Bridge\Tracy\Model;

require \dirname(__DIR__, 4).'/bootstrap.php';

use Raneomik\NetteMercure\Bridge\Tracy\Value\HubData;
use Raneomik\NetteMercure\Bridge\Tracy\Value\HubDatum;
use Raneomik\NetteMercure\Core\Publish\Broadcasters;
use Raneomik\NetteMercure\Core\Publish\PlainBroadcaster;
use Raneomik\NetteMercure\Core\Publish\Tracy\TraceableBroadcaster;
use Tester\Assert;
use Tester\TestCase;
use Tests\Fixtures\Dummies\Core\MockHubFactory;

/**
 * @testCase
 */
final class HubDataTest extends TestCase
{
    public function testMinimalistOptions(): void
    {
        $broadcasters = new Broadcasters([
            'test' => new PlainBroadcaster(
                MockHubFactory::create('http://hub.example.com'),
            ),
        ]);

        $hubData = new HubData($broadcasters);

        Assert::type(HubData::class, $hubData);
        Assert::falsey($hubData->count());
    }

    public function testRelevantData(): void
    {
        $plainBro = new PlainBroadcaster(
            MockHubFactory::create('http://hub.example.com'),
        );
        $anotherBro = new PlainBroadcaster(
            MockHubFactory::create('http://hub2.example.com'),
        );

        $broadcasters = new Broadcasters([
            'test' => new TraceableBroadcaster($plainBro),
            'excluded' => $anotherBro,
            'test2' => new TraceableBroadcaster($anotherBro),
        ]);

        $broadcasters->broadcast('test', 'test');
        $broadcasters->broadcast('test', 'test', [
            'hub' => 'test2',
        ]);

        $hubData = new HubData($broadcasters);

        Assert::count(2, $hubData);
        Assert::true($hubData->offsetExists('test'));
        Assert::true($hubData->offsetExists('test2'));
        Assert::false($hubData->offsetExists('excluded'));

        $totalDuration = 0;
        $totalMemory = 0;

        /** @var HubDatum $hubDatum */
        foreach ($hubData as $hubDatum) {
            Assert::type(HubDatum::class, $hubDatum);
            $totalMemory += $memory = $hubDatum->memory;
            $totalDuration += $duration = $hubDatum->duration;
            Assert::notEqual(0.0, $duration);
            Assert::notEqual(0.0, $memory);
            Assert::notEqual([], $hubDatum->broadcastData);
        }

        /** @var HubDatum $datum1 */
        $datum1 = $hubData['test'];

        /** @var HubDatum $datum2 */
        $datum2 = $hubData['test2'];

        Assert::same(1, $datum1->messageCount());
        Assert::same(1, $datum2->messageCount());
        Assert::notEqual($datum1->memory, $hubData->totalMemory);
        Assert::notEqual($datum1->duration, $hubData->totalDuration);
        Assert::notEqual($datum2->memory, $hubData->totalMemory);
        Assert::notEqual($datum2->duration, $hubData->totalDuration);
        Assert::equal($totalMemory, $hubData->totalMemory);
        Assert::equal($totalDuration, $hubData->totalDuration);
    }
}

(new HubDataTest())->run();
