<?php

declare(strict_types=1);

namespace Tests\Unit\Raneomik\NetteMercure\Bridge\Latte;

require \dirname(__DIR__, 3) . '/bootstrap.php';

use Raneomik\NetteMercure\Bridge\DI\Config\Configuration;
use Raneomik\NetteMercure\Bridge\DI\Config\ConfigurationRegistry;
use Raneomik\NetteMercure\Bridge\Latte\Function\HotReload;
use Raneomik\NetteMercure\Bridge\Latte\Function\Mercure;
use Raneomik\NetteMercure\Bridge\Latte\Function\MercureJWTToken;
use Raneomik\NetteMercure\Bridge\Latte\MercureExtension;
use Raneomik\NetteMercure\Bridge\Utils\BroadcastersLoader;
use Raneomik\NetteMercure\Core\Publish\Broadcasters;
use Raneomik\NetteMercure\Core\Publish\PlainBroadcaster;
use Symfony\Component\Mercure\Exception\InvalidArgumentException;
use Symfony\Component\Mercure\ProtocolVersion;
use Tester\Assert;
use Tester\TestCase;
use Tests\Fixtures\Dummies\Core\MockHubFactory;
use Tests\Fixtures\Dummies\Core\Subscribe\DummyJwtProvider;

/**
 * @testCase
 */
final class MercureExtensionTest extends TestCase
{
    private const string HOT_RELOAD_URL = 'http://v1.example.com/?match=hot-reload';

    private static Broadcasters $broadcasters;

    private static MercureExtension $extension;

    protected function setUp(): void
    {
        self::$broadcasters ??= new Broadcasters([
            'test' => new PlainBroadcaster(
                MockHubFactory::create('http://hub.example.com'),
            ),
            'v1' => new PlainBroadcaster(
                MockHubFactory::create(
                    'http://v1.example.com',
                    protocolVersion: ProtocolVersion::V1,
                ),
            ),
        ]);

        self::$extension ??= new MercureExtension(
            new DummyJwtProvider(),
            new BroadcastersLoader(static fn (): Broadcasters => self::$broadcasters),
            new ConfigurationRegistry([
                'test' => new Configuration(
                    hubName: 'test',
                    hubUrl: 'http://hub.example.com',
                    publicUrl: 'http://hub.example.com',
                    subscribe: ['*'],
                    publish: ['*'],
                    protocolVersion: ProtocolVersion::Legacy,
                ),
                'v1' => new Configuration(
                    hubName: 'v1',
                    hubUrl: 'http://v1.example.com',
                    publicUrl: 'http://v1.example.com',
                    subscribe: ['*'],
                    publish: ['*'],
                    jwtInQueryParam: true,
                ),
            ]),
            self::HOT_RELOAD_URL,
        );
    }

    public function testExtensionFunctions(): void
    {
        /**
         * @var Mercure $mercureFunction
         * @var MercureJWTToken $JWTTokenFunction
         * @var HotReload $hotReloadHeadersFunction
         */
        [
            'mercure' => $mercureFunction,
            'mercureJWTToken' => $JWTTokenFunction,
            'hotReloadMetaHead' => $hotReloadHeadersFunction,
        ] = self::$extension->getFunctions();

        Assert::type('callable', $mercureFunction);
        Assert::same(
            'http://hub.example.com?topic=test&authorization=dummy-jwt-token-test-provider-token-*-',
            $mercureFunction('test', options: [
                'addJwt' => true,
            ]),
        );
        Assert::same(
            'http://v1.example.com?match=test&authorization=dummy-jwt-token-test-provider-token-*-',
            $mercureFunction('test', hub: 'v1'),
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
            'dummy-jwt-token-test-provider-token-foo|bar-',
            $JWTTokenFunction(subscribe: ['foo', 'bar']),
        );
        Assert::same(
            'dummy-jwt-token-test-provider-token-*-',
            $JWTTokenFunction(),
        );

        Assert::type('callable', $hotReloadHeadersFunction);
        Assert::same(
            (string) HotReload::build(self::HOT_RELOAD_URL)(),
            (string) $hotReloadHeadersFunction(),
        );
    }

    public function testExtensionFunctionsV1ProtocolVersion(): void
    {
        /**
         * @var Mercure $mercureFunction
         * @var MercureJWTToken $JWTTokenFunction
         */
        [
            'mercure' => $mercureFunction,
            'mercureJWTToken' => $JWTTokenFunction,
        ] = self::$extension->getFunctions();

        Assert::same(
            'http://v1.example.com?match=test&authorization=dummy-jwt-token-test-provider-token-*-',
            $mercureFunction('test', hub: 'v1'),
        );
        Assert::same(
            'http://v1.example.com?match_urlpattern=foo&match_urlpattern=bar&lastEventID=987&authorization=dummy-jwt-token-test-provider-token-*-',
            $mercureFunction([
                'urlpattern' => ['foo', 'bar'],
            ], hub: 'v1', options: [
                'lastEventId' => '987',
            ]),
        );

        Assert::same(
            'dummy-jwt-token-test-provider-token-foo|bar-',
            $JWTTokenFunction(
                [
                    'test' => ['foo', 'bar'],
                ],
                hub: 'v1',
            ),
        );
        Assert::same(
            'dummy-jwt-token-test-provider-token-test-',
            $JWTTokenFunction([
                'urlpattern' => 'test',
            ], hub: 'v1'),
        );

        Assert::exception(
            static fn (): string => $mercureFunction([
                67 => ['foo', 'bar'],
            ], hub: 'v1'),
            InvalidArgumentException::class,
            '~Topics must be either a flat list of exact topics or an associative array~',
        );

        Assert::exception(
            static fn (): string => $mercureFunction([
                'test' => 'test',
            ], hub: 'test'),
            InvalidArgumentException::class,
        );
        Assert::same(
            'http://hub.example.com?topic=test&lastEventID=123',
            $mercureFunction(
                [
                    'exact' => ['test'],
                ],
                hub: 'test',
                options: [
                    'lastEventId' => '123',
                ],
            ),
        );
    }
}

(new MercureExtensionTest())->run();
