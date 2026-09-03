<?php

declare(strict_types=1);

namespace Tests\Unit\Raneomik\NetteMercure\Bridge\Latte;

require \dirname(__DIR__, 3).'/bootstrap.php';

use Raneomik\NetteMercure\Bridge\Latte\MercureExtension;
use Raneomik\NetteMercure\Bridge\Utils\BroadcastersLoader;
use Raneomik\NetteMercure\Bridge\Utils\ConfiguredData;
use Raneomik\NetteMercure\Bridge\Utils\ConfiguredDataRegistry;
use Raneomik\NetteMercure\Core\Publish\Broadcasters;
use Raneomik\NetteMercure\Core\Publish\PlainBroadcaster;
use Tester\Assert;
use Tester\TestCase;
use Tests\Fixtures\Dummies\Core\MockHubFactory;
use Tests\Fixtures\Dummies\Core\Subscribe\DummyJwtProvider;

/**
 * @testCase
 */
final class MercureExtensionTest extends TestCase
{
    private static ?Broadcasters $broadcasters = null;

    private static ?MercureExtension $extension = null;

    protected function setUp(): void
    {
        self::$broadcasters ??= new Broadcasters([
            'test' => new PlainBroadcaster(
                MockHubFactory::create('http://hub.example.com'),
            ),
        ]);

        self::$extension ??= new MercureExtension(
            new DummyJwtProvider(),
            new BroadcastersLoader(static fn (): Broadcasters => self::$broadcasters),
            new ConfiguredDataRegistry([
                'test' => new ConfiguredData(
                    'test',
                    'http://hub.example.com',
                    ['*'],
                    ['*'],
                    false,
                    false,
                    false,
                ),
            ]),
        );
    }

    public function testExtensionDependencyLoad(): void
    {
        Assert::same(self::$broadcasters, self::$extension->broadcasters());
    }

    public function testExtensionFunctions(): void
    {
        [
            'mercure' => $mercureFunction,
            'mercureJWTToken' => $JWTTokenFunction,
        ] = self::$extension->getFunctions();

        Assert::type('callable', $mercureFunction);
        Assert::same(
            'http://hub.example.com?topic=test&authorization=dummy-jwt-token-test-provider-token-*-',
            $mercureFunction('test', 'test', options: [
                'addJwt' => true,
            ]),
        );
        Assert::same(
            'http://hub.example.com?topic=test&lastEventID=123',
            $mercureFunction('test', hub: 'test', options: [
                'lastEventId' => '123',
            ]),
        );
        Assert::same(
            'http://hub.example.com?lastEventID=123',
            $mercureFunction(hub: 'test', options: [
                'lastEventId' => '123',
            ]),
        );

        Assert::type('callable', $JWTTokenFunction);
        Assert::same(
            'dummy-jwt-token-test-provider-token-*-',
            $JWTTokenFunction(),
        );
        Assert::same(
            'dummy-jwt-token-test-provider-token-foo|bar-',
            $JWTTokenFunction(subscribe: ['foo', 'bar']),
        );
    }
}

(new MercureExtensionTest())->run();
