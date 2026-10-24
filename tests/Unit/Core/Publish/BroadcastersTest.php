<?php

declare(strict_types=1);

namespace Tests\Unit\Raneomik\NetteMercure\Core\Publish;

require \dirname(__DIR__, 3) . '/bootstrap.php';

use Nette\Utils\Json;
use Raneomik\NetteMercure\Core\Publish\Broadcasters;
use Raneomik\NetteMercure\Core\Publish\PlainBroadcaster;
use Raneomik\NetteMercure\Exception\BroadcastException;
use Symfony\Component\Mercure\HubInterface;
use Tester\Assert;
use Tester\TestCase;
use Tests\Fixtures\Dummies\Core\MockHubFactory;

/**
 * @testCase
 */
final class BroadcastersTest extends TestCase
{
    /**
     * @var array<string,HubInterface>
     */
    private array $broadcasterHubs;

    private Broadcasters $broadcasters;

    protected function setUp(): void
    {
        $this->broadcasterHubs = [
            'hub1' => MockHubFactory::create('http://hub1.example.com'),
            'hub2' => MockHubFactory::create('http://hub2.example.com'),
            'hub3' => MockHubFactory::create('http://hub3.example.com'),
        ];
        $this->broadcasters = new Broadcasters(array_combine(
            array_keys($this->broadcasterHubs),
            array_map(static fn (HubInterface $hub): PlainBroadcaster => new PlainBroadcaster($hub), $this->broadcasterHubs),
        ));
    }

    public function testMinimalisticBroadcast(): void
    {
        Assert::same(
            Json::encode([
                'data' => 'Hello, World!',
                'topics' => ['test'],
            ]),
            $this->broadcasters->broadcast(
                'test',
                'Hello, World!',
            ),
        );
    }

    public function testMovingHubsBroadcast(): void
    {
        Assert::same(
            Json::encode([
                'data' => '{"message": "Hello, World!"}',
                'topics' => ['test'],
            ]),
            $this->broadcasters->broadcast(
                'test',
                '{"message": "Hello, World!"}',
                [
                    'hub' => 'hub2',
                ],
            ),
        );

        // a broadcast to a named hub must not change the default one
        Assert::same($this->broadcasterHubs['hub1'], $this->broadcasters->broadcasterHub());
        Assert::same('http://hub1.example.com', $this->broadcasters->broadcasterUrl());

        Assert::same(
            Json::encode([
                'data' => '{"message": "HI"}',
                'topics' => ['test'],
            ]),
            $this->broadcasters->broadcast(
                'test',
                '{"message": "HI"}',
            ),
        );

        Assert::same('http://hub1.example.com', $this->broadcasters->broadcasterUrl());

        Assert::same(
            Json::encode([
                'data' => '{"message": "Bye!"}',
                'topics' => ['test'],
            ]),
            $this->broadcasters->broadcast(
                'test',
                '{"message": "Bye!"}',
                [
                    'hub' => 'hub3',
                ],
            ),
        );

        Assert::same('http://hub1.example.com', $this->broadcasters->broadcasterUrl());
    }

    public function testBroadcastToAll(): void
    {
        $output = Json::encode([
            'data' => '{"message": "Hello, World!"}',
            'topics' => ['test'],
        ]);
        Assert::same(
            implode(';', [$output, $output, $output]),
            $this->broadcasters->broadcast(
                'test',
                '{"message": "Hello, World!"}',
                toAll: true
            ),
        );

        Assert::same(3, $this->broadcasters->count());
        Assert::same($this->broadcasterHubs['hub2'], $this->broadcasters->broadcasterHub('hub2'));
        Assert::same('http://hub2.example.com', $this->broadcasters->broadcasterUrl('hub2'));
        Assert::same(
            [
                'rendered_data' => '{"message": "Hello, World!"}',
            ],
            $this->broadcasters->broadcastOptions()
        );
        Assert::same(
            $this->broadcasters->broadcastOptions('hub2'),
            $this->broadcasters->broadcastOptions()
        );
    }

    /**
     * @return iterable<string, array{
     *     call: \Closure(Broadcasters): mixed,
     * }>
     */
    public function provideUnknownHubCalls(): iterable
    {
        yield 'broadcast' => [
            'call' => static fn (Broadcasters $broadcasters): string => $broadcasters->broadcast(
                'test',
                '{"message": "Hello, World!"}',
                [
                    'hub' => 'inexistent',
                ],
            ),
        ];

        yield 'broadcasterHub' => [
            'call' => static fn (Broadcasters $broadcasters): HubInterface => $broadcasters->broadcasterHub('inexistent'),
        ];

        yield 'broadcasterUrl' => [
            'call' => static fn (Broadcasters $broadcasters): string => $broadcasters->broadcasterUrl('inexistent'),
        ];

        yield 'broadcastOptions' => [
            'call' => static fn (Broadcasters $broadcasters): array => $broadcasters->broadcastOptions('inexistent'),
        ];
    }

    /**
     * @dataProvider provideUnknownHubCalls
     *
     * @param \Closure(Broadcasters): mixed $call
     */
    public function testUnknownHub(\Closure $call): void
    {
        Assert::exception(
            fn (): mixed => $call($this->broadcasters),
            BroadcastException::class,
            'The hub "inexistent" is not defined.',
        );
    }

    /**
     * @return iterable<string, array{
     *     call: \Closure(Broadcasters): mixed,
     *     exception: class-string<\Throwable>,
     *     message: string,
     * }>
     */
    public function provideEmptyBroadcastersCalls(): iterable
    {
        yield 'broadcast' => [
            'call' => static fn (Broadcasters $broadcasters): string => $broadcasters->broadcast(
                'test',
                '{"message": "Hello, World!"}',
            ),
            'exception' => BroadcastException::class,
            'message' => 'No broadcaster defined.',
        ];

        yield 'broadcasterUrl' => [
            'call' => static fn (Broadcasters $broadcasters): string => $broadcasters->broadcasterUrl(),
            'exception' => BroadcastException::class,
            'message' => 'No broadcaster defined.',
        ];

        yield 'broadcastOptions' => [
            'call' => static fn (Broadcasters $broadcasters): array => $broadcasters->broadcastOptions(),
            'exception' => BroadcastException::class,
            'message' => 'No broadcaster defined.',
        ];

        yield 'offsetSet' => [
            'call' => static fn (Broadcasters $broadcasters) => $broadcasters->offsetSet('test', new PlainBroadcaster(MockHubFactory::create('http://hub.example.com'))),
            'exception' => \LogicException::class,
            'message' => 'Cannot modify readonly collection.',
        ];

        yield 'offsetUnset' => [
            'call' => static fn (Broadcasters $broadcasters) => $broadcasters->offsetUnset('test'),
            'exception' => \LogicException::class,
            'message' => 'Cannot modify readonly collection.',
        ];
    }

    /**
     * @dataProvider provideEmptyBroadcastersCalls
     *
     * @param \Closure(Broadcasters): mixed $call
     * @param class-string<\Throwable> $exception
     */
    public function testEmptyBroadcasters(\Closure $call, string $exception, string $message): void
    {
        Assert::exception(
            static fn (): mixed => $call(new Broadcasters([])),
            $exception,
            $message,
        );
    }
}

(new BroadcastersTest())->run();
