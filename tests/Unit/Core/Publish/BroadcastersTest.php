<?php

declare(strict_types=1);

namespace Tests\Unit\Raneomik\NetteMercure\Core\Publish;

require \dirname(__DIR__, 3).'/bootstrap.php';

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

        Assert::same($this->broadcasterHubs['hub2'], $this->broadcasters->broadcasterHub());
        Assert::same('http://hub2.example.com', $this->broadcasters->broadcasterUrl());

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

        Assert::same('http://hub3.example.com', $this->broadcasters->broadcasterUrl());
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

    public function testNotExistentHub(): void
    {
        Assert::exception(
            fn (): string => $this->broadcasters->broadcast(
                'test',
                '{"message": "Hello, World!"}',
                [
                    'hub' => 'inexistent',
                ],
            ),
            BroadcastException::class,
            'The hub "inexistent" is not defined.'
        );

        Assert::exception(
            fn (): string => $this->broadcasters->broadcasterUrl('inexistent'),
            BroadcastException::class,
            'The hub "inexistent" is not defined.'
        );

        Assert::exception(
            fn (): array => $this->broadcasters->broadcastOptions('inexistent'),
            BroadcastException::class,
            'The hub "inexistent" is not defined.'
        );
    }

    public function testEmptyBroadcasters(): void
    {
        $broadcasters = new Broadcasters([]);

        Assert::exception(
            static fn (): string => $broadcasters->broadcast(
                'test',
                '{"message": "Hello, World!"}',
            ),
            BroadcastException::class,
            'No broadcaster defined.'
        );

        Assert::exception(
            static fn (): string => $broadcasters->broadcasterUrl(),
            BroadcastException::class,
            'No broadcaster defined.'
        );

        Assert::exception(
            static fn (): array => $broadcasters->broadcastOptions(),
            BroadcastException::class,
            'No broadcaster defined.'
        );

        Assert::exception(
            // @phpstan-ignore-next-line
            static fn (): string => $broadcasters['test'] = 'test',
            \LogicException::class,
            'Cannot modify readonly collection.'
        );

        Assert::exception(
            static fn () => $broadcasters->offsetUnset('test'),
            \LogicException::class,
            'Cannot modify readonly collection.'
        );
    }
}

(new BroadcastersTest())->run();
