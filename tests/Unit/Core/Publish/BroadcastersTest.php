<?php

declare(strict_types=1);

namespace Tests\Unit\Raneomik\NetteMercure\Core;

require \dirname(__DIR__, 3).'/bootstrap.php';

use Nette\Utils\Json;
use Raneomik\NetteMercure\Core\Publish\Broadcasters;
use Raneomik\NetteMercure\Core\Publish\PlainBroadcaster;
use Raneomik\NetteMercure\Exception\BroadcastException;
use Symfony\Component\Mercure\Jwt\StaticTokenProvider;
use Symfony\Component\Mercure\MockHub;
use Symfony\Component\Mercure\Update;
use Tester\Assert;
use Tester\TestCase;

final class BroadcastersTest extends TestCase
{
    private Broadcasters $broadcasters;

    protected function setUp(): void
    {
        $publishCallback = static fn (Update $update): string => Json::encode([
            'data' => $update->getData(),
            'topics' => $update->getTopics(),
        ]);

        $this->broadcasters = new Broadcasters(
            [
                'hub1' => new PlainBroadcaster(
                    new MockHub(...[
                        'http://hub1.example.com',
                        new StaticTokenProvider('!ChangeMe1!'),
                        $publishCallback,
                    ]),
                ),
                'hub2' => new PlainBroadcaster(
                    new MockHub(...[
                        'http://hub2.example.com',
                        new StaticTokenProvider('!ChangeMe2!'),
                        $publishCallback,
                    ]),
                ),
                'hub3' => new PlainBroadcaster(
                    new MockHub(...[
                        'http://hub3.example.com',
                        new StaticTokenProvider('!ChangeMe3!'),
                        $publishCallback,
                    ]),
                ),
            ]
        );
    }

    /**
     * @testCase
     */
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

    /**
     * @testCase
     */
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

    /**
     * @testCase
     */
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
        Assert::same('http://hub2.example.com', $this->broadcasters->broadcasterUrl('hub2'));
        Assert::same(
            [
                'rendered_data' => '{"message": "Hello, World!"}',
            ],
            $this->broadcasters->broadcastOptions()
        );
    }

    /**
     * @testCase
     */
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

    /**
     * @testCase
     */
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
