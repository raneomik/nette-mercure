<?php

declare(strict_types=1);

namespace Tests\Unit\Raneomik\NetteMercure\Core\Publish;

require \dirname(__DIR__, 3).'/bootstrap.php';

use Nette\Utils\Json;
use Raneomik\NetteMercure\Core\Publish\PlainBroadcaster;
use Symfony\Component\Mercure\HubInterface;
use Tester\Assert;
use Tester\TestCase;
use Tests\Fixtures\Dummies\Core\MockHubFactory;

/**
 * @testCase
 */
final class PlainBroadcasterTest extends TestCase
{
    private HubInterface $broadcasterHub;

    private PlainBroadcaster $broadcaster;

    protected function setUp(): void
    {
        $this->broadcaster = new PlainBroadcaster(
            $this->broadcasterHub = MockHubFactory::create('http://example.com/hub'),
        );
    }

    public function testMinimalisticBroadcast(): void
    {
        Assert::same(
            Json::encode([
                'data' => 'Hello, World!',
                'topics' => ['test'],
            ]),
            $this->broadcaster->broadcast(
                'test',
                'Hello, World!',
            ),
        );

        Assert::same($this->broadcasterHub, $this->broadcaster->broadcasterHub());
        Assert::same('http://example.com/hub', $this->broadcaster->broadcasterUrl());
        Assert::same([
            'rendered_data' => 'Hello, World!',
        ], $this->broadcaster->broadcastOptions());

        Assert::same(
            Json::encode([
                'data' => '{"message":"test"}',
                'topics' => ['test'],
            ]),
            $this->broadcaster->broadcast(
                'test',
                [
                    'message' => 'test',
                ],
            ),
        );
        Assert::same([
            'rendered_data' => '{"message":"test"}',
        ], $this->broadcaster->broadcastOptions());
    }

    public function testJsonBroadcast(): void
    {
        Assert::same(
            Json::encode([
                'data' => '{"message": "Hello, World!"}',
                'topics' => ['test'],
            ]),
            $this->broadcaster->broadcast(
                'test',
                '{"message": "Hello, World!"}',
            ),
        );
    }
}

(new PlainBroadcasterTest())->run();
