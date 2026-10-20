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

    /**
     * @return iterable<string, array{
     *     topics: mixed,
     *     hub: ?string,
     *     options: array<string, mixed>,
     *     expected: string,
     * }>
     */
    public function provideUrls(): iterable
    {
        yield 'legacy, forced jwt' => [
            'topics' => 'test',
            'hub' => null,
            'options' => [
                'addJwt' => true,
            ],
            'expected' => 'http://hub.example.com?topic=test&authorization=dummy-jwt-token-test-provider-token-*-',
        ];

        yield 'legacy, lastEventId' => [
            'topics' => 'test',
            'hub' => 'test',
            'options' => [
                'lastEventId' => '123',
            ],
            'expected' => 'http://hub.example.com?topic=test&lastEventID=123',
        ];

        yield 'legacy, "exact" matcher' => [
            'topics' => [
                'exact' => ['test'],
            ],
            'hub' => 'test',
            'options' => [
                'lastEventId' => '123',
            ],
            'expected' => 'http://hub.example.com?topic=test&lastEventID=123',
        ];

        yield 'no topic' => [
            'topics' => null,
            'hub' => 'test',
            'options' => [
                'lastEventId' => '123',
            ],
            'expected' => 'http://hub.example.com?lastEventID=123',
        ];

        yield '1.0, matcher type' => [
            'topics' => [
                'urlpattern' => ['foo', 'bar'],
            ],
            'hub' => 'v1',
            'options' => [
                'lastEventId' => '987',
            ],
            'expected' => 'http://v1.example.com?match_urlpattern=foo&match_urlpattern=bar&lastEventID=987&authorization=dummy-jwt-token-test-provider-token-*-',
        ];

        yield 'hub from options' => [
            'topics' => 'test',
            'hub' => null,
            'options' => [
                'hub' => 'v1',
            ],
            'expected' => 'http://v1.example.com?match=test&authorization=dummy-jwt-token-test-provider-token-*-',
        ];
    }

    /**
     * @dataProvider provideUrls
     *
     * @param array<string, mixed> $options
     */
    public function testMercure(mixed $topics, ?string $hub, array $options, string $expected): void
    {
        Assert::same($expected, $this->function('mercure')($topics, $hub, $options));
    }

    /**
     * @return iterable<string, array{
     *     topics: mixed,
     *     hub: string,
     *     message: string,
     * }>
     */
    public function provideInvalidTopics(): iterable
    {
        yield '1.0, integer matcher key' => [
            'topics' => [
                67 => ['foo', 'bar'],
            ],
            'hub' => 'v1',
            'message' => '~Topics must be either a flat list of exact topics or an associative array~',
        ];

        yield 'legacy, non "exact" matcher' => [
            'topics' => [
                'test' => 'test',
            ],
            'hub' => 'test',
            'message' => '~require the Mercure protocol 1.0~',
        ];

        yield 'nested topics' => [
            'topics' => [['test']],
            'hub' => 'v1',
            'message' => '~Topics must be strings~',
        ];
    }

    /**
     * @dataProvider provideInvalidTopics
     */
    public function testMercureInvalidTopics(mixed $topics, string $hub, string $message): void
    {
        $mercure = $this->function('mercure');

        Assert::exception(static fn () => $mercure($topics, $hub), InvalidArgumentException::class, $message);
    }

    /**
     * @return iterable<string, array{
     *     subscribe: mixed,
     *     hub: ?string,
     *     expected: string,
     * }>
     */
    public function provideJwtTokens(): iterable
    {
        yield 'default grants' => [
            'subscribe' => ['*'],
            'hub' => null,
            'expected' => 'dummy-jwt-token-test-provider-token-*-',
        ];

        yield 'topics' => [
            'subscribe' => ['foo', 'bar'],
            'hub' => null,
            'expected' => 'dummy-jwt-token-test-provider-token-foo|bar-',
        ];

        yield '1.0, matcher map' => [
            'subscribe' => [
                'test' => ['foo', 'bar'],
            ],
            'hub' => 'v1',
            'expected' => 'dummy-jwt-token-test-provider-token-foo|bar-',
        ];

        yield '1.0, matcher string' => [
            'subscribe' => [
                'urlpattern' => 'test',
            ],
            'hub' => 'v1',
            'expected' => 'dummy-jwt-token-test-provider-token-test-',
        ];
    }

    /**
     * @dataProvider provideJwtTokens
     */
    public function testMercureJWTToken(mixed $subscribe, ?string $hub, string $expected): void
    {
        $token = $this->function('mercureJWTToken');

        Assert::same($expected, $token($subscribe, [], $hub));
    }

    public function testHotReload(): void
    {
        $hotReloadHeadersFunction = $this->function('hotReloadMetaHead');

        Assert::same(
            (string) HotReload::build(self::HOT_RELOAD_URL)(),
            (string) $hotReloadHeadersFunction(),
        );
        Assert::notSame(
            '',
            (string) $hotReloadHeadersFunction(),
        );
    }

    public function testExtensionWithoutHotReloadUrl(): void
    {
        self::$extension = new MercureExtension(
            new DummyJwtProvider(),
            new BroadcastersLoader(static fn (): Broadcasters => self::$broadcasters),
            new ConfigurationRegistry(
                [
                    'test' => new Configuration(
                        hubName: 'test',
                        hubUrl: 'http://hub.example.com',
                        publicUrl: 'http://hub.example.com',
                        subscribe: ['*'],
                        publish: ['*'],
                        protocolVersion: ProtocolVersion::Legacy,
                    ),
                ],
            ),
        );

        $hotReloadHeadersFunction = $this->function('hotReloadMetaHead');
        Assert::same(
            '',
            (string) $hotReloadHeadersFunction(),
        );
    }

    private function function(string $name): callable
    {
        $function = self::$extension->getFunctions()[$name];
        Assert::type('callable', $function);

        return $function;
    }
}

(new MercureExtensionTest())->run();
