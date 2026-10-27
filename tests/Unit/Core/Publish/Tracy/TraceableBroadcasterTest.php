<?php

declare(strict_types=1);

namespace Tests\Unit\Raneomik\NetteMercure\Core\Publish\Tracy;

require \dirname(__DIR__, 4) . '/bootstrap.php';

use Nette\Utils\Json;
use Raneomik\NetteMercure\Core\Publish\Latte\TurboStreamAction;
use Raneomik\NetteMercure\Core\Publish\PlainBroadcaster;
use Raneomik\NetteMercure\Core\Publish\Tracy\Metrics;
use Raneomik\NetteMercure\Core\Publish\Tracy\TraceableBroadcaster;
use Raneomik\NetteMercure\Core\Publish\Tracy\Value\BroadcastData;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;
use Tester\Assert;
use Tester\TestCase;
use Tests\Fixtures\Dummies\Core\MockHubFactory;
use Tests\Fixtures\Dummies\Core\Publish\DummyBroadcaster;

/**
 * @testCase
 */
final class TraceableBroadcasterTest extends TestCase
{
    private HubInterface $broadcasterHub;

    private TraceableBroadcaster $debugBroadcaster;

    private Metrics $metrics;

    protected function setUp(): void
    {
        $this->debugBroadcaster = new TraceableBroadcaster(
            new PlainBroadcaster(
                $this->broadcasterHub = MockHubFactory::create('http://example.com/hub'),
            ),
            $this->metrics = new Metrics(),
        );
    }

    public function testBroadcastTracing(): void
    {
        Assert::same(
            Json::encode([
                'data' => 'Hello, World!',
                'topics' => ['test'],
            ]),
            $this->debugBroadcaster->broadcast(
                'test',
                'Hello, World!',
            ),
        );

        Assert::same($this->broadcasterHub, $this->debugBroadcaster->broadcasterHub());
        Assert::same('http://example.com/hub', $this->debugBroadcaster->broadcasterUrl());
        Assert::same([
            'rendered_data' => 'Hello, World!',
        ], $this->debugBroadcaster->broadcastOptions());

        Assert::same(
            $firstMessageMemory = $this->metrics->getMemory($this->debugBroadcaster::class),
            $this->debugBroadcaster->getMemory(),
        );
        Assert::same(
            $firstMessageDuration = $this->metrics->getDuration($this->debugBroadcaster::class),
            $this->debugBroadcaster->getDuration(),
        );
        Assert::same(1, $this->debugBroadcaster->count());
        Assert::count(1, $this->debugBroadcaster->broadcastData());

        $this->debugBroadcaster->broadcast(
            'test',
            'Hello, another World!',
            [
                'action' => TurboStreamAction::Update,
                'metadata' => 'second broadcast',
            ],
            template: 'uncosidered_in_plain_broadcaster',
        );

        Assert::same(
            $firstMessageMemory + $this->metrics->getMemory($this->debugBroadcaster::class),
            $this->debugBroadcaster->getMemory(),
        );
        Assert::same(
            $firstMessageDuration + $this->metrics->getDuration($this->debugBroadcaster::class),
            $this->debugBroadcaster->getDuration(),
        );

        Assert::same(2, $this->debugBroadcaster->count());
        Assert::count(2, $this->debugBroadcaster->broadcastData());

        $formatedData = array_map(
            static fn (BroadcastData $data): array => [
                'topics' => $data->topics,
                'data' => $data->data,
                'template' => $data->template(),
                'action' => $data->action(),
                'options' => $data->options(),
            ],
            $this->debugBroadcaster->broadcastData(),
        );
        Assert::same(
            [
                [
                    'topics' => ['test'],
                    'data' => 'Hello, World!',
                    'template' => null,
                    'action' => null,
                    'options' => [],
                ],
                [
                    'topics' => ['test'],
                    'data' => 'Hello, another World!',
                    'template' => 'uncosidered_in_plain_broadcaster',
                    'action' => TurboStreamAction::Update,
                    'options' => [
                        'metadata' => 'second broadcast',
                    ],
                ],
            ],
            $formatedData,
        );
    }

    public function testErrorLog(): void
    {
        $broadcast = new TraceableBroadcaster(
            new DummyBroadcaster(
                MockHubFactory::create(
                    'http://example.com/hub',
                ),
                static fn (): string => throw new \RuntimeException('publish error'),
            ),
            new Metrics(),
        );

        $broadcast->broadcast('test', 'Hello, error!');

        $formatedData = array_map(
            static fn (BroadcastData $data): ?string => $data->errorMessage(),
            $broadcast->broadcastData(),
        );

        Assert::same(['publish error'], $formatedData);
    }

    public function testEveryPublicationIsAnnouncedOnTheDebugTopic(): void
    {
        $published = [];
        $hub = MockHubFactory::create(
            'http://example.com/hub',
            publisher: static function (Update $update) use (&$published): string {
                $published[] = $update;

                return 'urn:uuid:' . \count($published);
            },
        );

        $broadcaster = new TraceableBroadcaster(new PlainBroadcaster($hub), new Metrics(), 'one');

        Assert::same('urn:uuid:1', $broadcaster->broadcast('game/42', 'é<b>', [
            'sse_type' => 'chat',
            'private' => true,
        ]));

        Assert::count(2, $published, 'the real update, then its announcement');
        Assert::same(['game/42'], $published[0]->getTopics());

        $announcement = $published[1];
        Assert::same([TraceableBroadcaster::DEBUG_TOPIC], $announcement->getTopics());
        Assert::same(TraceableBroadcaster::DEBUG_EVENT, $announcement->getType());
        Assert::false($announcement->isPrivate(), 'the debug channel itself is public');

        $envelope = Json::decode($announcement->getData(), forceArrays: true);
        Assert::same('urn:uuid:1', $envelope['id'], 'same id as the one received by the subscribers');
        Assert::same('one', $envelope['hub']);
        Assert::same('sent', $envelope['status']);
        Assert::same(['game/42'], $envelope['topics']);
        Assert::same('chat', $envelope['type']);
        Assert::true($envelope['private']);
        Assert::same(5, (int) $envelope['size'], 'size is expressed in bytes, not in characters');
        Assert::same('é<b>', $envelope['preview']);
        Assert::null($envelope['error']);
        Assert::true(5000 > abs($envelope['sentAt'] - microtime(true) * 1000), 'sentAt is a wall clock, in ms');

        Assert::same(1, $broadcaster->count(), 'the announcement is not traced as a message');
    }

    public function testTheAnnouncementOfAFailedPublicationCarriesTheError(): void
    {
        $published = [];
        $broadcast = new TraceableBroadcaster(
            new DummyBroadcaster(
                MockHubFactory::create(
                    'http://example.com/hub',
                    publisher: static function (Update $update) use (&$published): string {
                        $published[] = $update;

                        return 'debug-id';
                    },
                ),
                static fn (): string => throw new \RuntimeException('publish error'),
            ),
            new Metrics(),
            'one',
        );

        Assert::same('', $broadcast->broadcast('test', 'Hello, error!'));

        Assert::count(1, $published);
        $envelope = Json::decode($published[0]->getData(), forceArrays: true);
        Assert::same('error', $envelope['status']);
        Assert::null($envelope['id'], 'nothing was published, so nothing will ever be received');
        Assert::same([
            'class' => \RuntimeException::class,
            'message' => 'publish error',
        ], $envelope['error']);
    }

    public function testAFailingAnnouncementNeverBreaksTheRealPublication(): void
    {
        $hub = MockHubFactory::create(
            'http://example.com/hub',
            publisher: static function (Update $update): string {
                if (TraceableBroadcaster::DEBUG_TOPIC === $update->getTopics()[0]) {
                    throw new \RuntimeException('debug topic is not allowed');
                }

                return 'urn:uuid:1';
            },
        );

        $broadcaster = new TraceableBroadcaster(new PlainBroadcaster($hub), new Metrics());

        Assert::same('urn:uuid:1', $broadcaster->broadcast('test', 'Hello'));
        Assert::same(1, $broadcaster->count());
        Assert::same(\Raneomik\NetteMercure\Core\Publish\Tracy\Value\BroadcastStatus::Sent, $broadcaster->broadcastData()[0]->status);
    }
}

(new TraceableBroadcasterTest())->run();
