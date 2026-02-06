<?php

declare(strict_types=1);

namespace Tests\Unit\Raneomik\NetteMercure\Bridge\Tracy\Value;

require \dirname(__DIR__, 4).'/bootstrap.php';

use Raneomik\NetteMercure\Bridge\Tracy\Value\HubData;
use Raneomik\NetteMercure\Bridge\Tracy\Value\HubDatum;
use Raneomik\NetteMercure\Core\Publish\Broadcasters;
use Raneomik\NetteMercure\Core\Publish\PlainBroadcaster;
use Raneomik\NetteMercure\Core\Publish\Tracy\TraceableBroadcaster;
use Symfony\Component\Mercure\Jwt\StaticTokenProvider;
use Symfony\Component\Mercure\MockHub;
use Symfony\Component\Mercure\Update;
use Tester\Assert;
use Tester\TestCase;

final class HubDataTest extends TestCase
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
                    static fn (Update $update): string => $update->getData(),
                ),
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
        $publishCallback = static fn (Update $update): string => $update->getData();
        $plainBro = new PlainBroadcaster(
            new MockHub(
                'test',
                new StaticTokenProvider('!ChangeMe1!'),
                $publishCallback,
            ),
        );
        $anotherBro = new PlainBroadcaster(
            new MockHub(
                'test2',
                new StaticTokenProvider('!ChangeMe2!'),
                $publishCallback,
            ),
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

        Assert::same(1, $datum1->messageCount());
        Assert::same(1, $datum2->messageCount());
        Assert::notEqual(0.0, $hubData->totalDuration);
        Assert::notEqual(0.0, $hubData->totalMemory);
    }
}

(new HubDataTest())->run();
