<?php

declare(strict_types=1);

namespace Tests\Unit\Raneomik\NetteMercure\Core;

require \dirname(__DIR__, 2) . '/bootstrap.php';

use Raneomik\NetteMercure\Bridge\DI\Config\Configuration;
use Raneomik\NetteMercure\Bridge\DI\Config\ConfigurationRegistry;
use Raneomik\NetteMercure\Core\Discovery;
use Symfony\Component\WebLink\HttpHeaderSerializer;
use Tester\Assert;
use Tester\TestCase;
use Tests\Fixtures\Dummies\Core\DummyRequest;
use Tests\Fixtures\Dummies\Core\DummyResponse;

/**
 * @testCase
 */
final class DiscoveryTest extends TestCase
{
    /**
     * @return iterable<array{
     *     discovery: Discovery,
     *     response: DummyResponse,
     *     expectedHeaders: array<string,string>
     * }>
     */
    public function provideDiscoveries(): iterable
    {
        $defaultNoDiscoveryConfig = new Configuration(
            hubName: 'test',
            hubUrl: 'http://hub.example.com',
            publicUrl: 'http://hub.example.com',
            subscribe: ['*'],
            publish: ['*'],
        );
        $hub2Config = new Configuration(
            hubName: 'test',
            hubUrl: 'http://hub2.example.com',
            publicUrl: 'http://hub2.example.com',
            subscribe: ['*'],
            publish: ['*'],
            autoDiscovery: true,
        );

        yield 'minimalistic-no-discovery-default' => [
            'discovery' => new Discovery(
                new HttpHeaderSerializer(),
                new DummyRequest(),
                $response = new DummyResponse(),
                new ConfigurationRegistry([
                    'test' => $defaultNoDiscoveryConfig,
                ]),
            ),
            'response' => $response,
            'expectedHeaders' => [],
        ];

        yield 'minimalistic-default' => [
            'discovery' => new Discovery(
                new HttpHeaderSerializer(),
                new DummyRequest(),
                $response = new DummyResponse(),
                new ConfigurationRegistry([
                    'test' => $hub2Config,
                ]),
            ),
            'response' => $response,
            'expectedHeaders' => [
                'Access-Control-Expose-Headers' => 'Link',
                'Link' => '<http://hub2.example.com>; rel="mercure"',
            ],
        ];

        yield 'hub-query-in-prior-of-hubMame' => [
            'discovery' => new Discovery(
                new HttpHeaderSerializer(),
                new DummyRequest(fromUrl: '/?hubName=test&hub=test2'),
                $response = new DummyResponse(),
                new ConfigurationRegistry([
                    'test' => $defaultNoDiscoveryConfig,
                    'test2' => $hub2Config,
                ])
            ),
            'response' => $response,
            'expectedHeaders' => [
                'Access-Control-Expose-Headers' => 'Link',
                'Link' => '<http://hub2.example.com>; rel="mercure"',
            ],
        ];

        yield 'hub-query' => [
            'discovery' => new Discovery(
                new HttpHeaderSerializer(),
                new DummyRequest(fromUrl: '/?hub=test2'),
                $response = new DummyResponse(),
                new ConfigurationRegistry([
                    'test' => $defaultNoDiscoveryConfig,
                    'test2' => $hub2Config,
                ])
            ),
            'response' => $response,
            'expectedHeaders' => [
                'Access-Control-Expose-Headers' => 'Link',
                'Link' => '<http://hub2.example.com>; rel="mercure"',
            ],
        ];

        yield 'hubName-query-unknown-hub-ignored' => [
            'discovery' => new Discovery(
                new HttpHeaderSerializer(),
                new DummyRequest(fromUrl: '/?hubName=test2'),
                $response = new DummyResponse(),
                new ConfigurationRegistry([
                    'test' => $defaultNoDiscoveryConfig,
                ])
            ),
            'response' => $response,
            'expectedHeaders' => [],
        ];

        yield 'hub-query-but-no-discovery-query' => [
            'discovery' => new Discovery(
                new HttpHeaderSerializer(),
                new DummyRequest(fromUrl: '/?hub=test&' . Discovery::NO_LINK_DISCOVER . '=1'),
                $response = new DummyResponse(),
                new ConfigurationRegistry([
                    'test' => $hub2Config,
                ])
            ),
            'response' => $response,
            'expectedHeaders' => [],
        ];

        yield 'hub-query-but-no-discovery-header' => [
            'discovery' => new Discovery(
                new HttpHeaderSerializer(),
                new DummyRequest([
                    Discovery::NO_LINK_DISCOVER => '1',
                ], fromUrl: '/?hubName=test'),
                $response = new DummyResponse(),
                new ConfigurationRegistry([
                    'test' => $hub2Config,
                ])
            ),
            'response' => $response,
            'expectedHeaders' => [],
        ];

        yield 'hub-query-but-no-discovery-header-and-query' => [
            'discovery' => new Discovery(
                new HttpHeaderSerializer(),
                new DummyRequest([
                    Discovery::NO_LINK_DISCOVER => '1',
                ], fromUrl: '/?' . Discovery::NO_LINK_DISCOVER . '=1'),
                $response = new DummyResponse(),
                new ConfigurationRegistry([
                    'test' => $hub2Config,
                ])
            ),
            'response' => $response,
            'expectedHeaders' => [],
        ];

        yield 'preflight-attempt-with-options-method-only' => [
            'discovery' => new Discovery(
                new HttpHeaderSerializer(),
                new DummyRequest(method: 'OPTIONS'),
                $response = new DummyResponse(),
                new ConfigurationRegistry([
                    'test' => $hub2Config,
                ])
            ),
            'response' => $response,
            'expectedHeaders' => [
                'Access-Control-Expose-Headers' => 'Link',
                'Link' => '<http://hub2.example.com>; rel="mercure"',
            ],
        ];

        yield 'preflight-attempt-with-headers-only' => [
            'discovery' => new Discovery(
                new HttpHeaderSerializer(),
                new DummyRequest(
                    [
                        'Access-Control-Request-Method' => 'get',
                    ],
                ),
                $response = new DummyResponse(),
                new ConfigurationRegistry([
                    'test' => $hub2Config,
                ])
            ),
            'response' => $response,
            'expectedHeaders' => [
                'Access-Control-Expose-Headers' => 'Link',
                'Link' => '<http://hub2.example.com>; rel="mercure"',
            ],
        ];

        yield 'preflight-with-method-and-headers' => [
            'discovery' => new Discovery(
                new HttpHeaderSerializer(),
                new DummyRequest(
                    [
                        'Access-Control-Request-Method' => 'get',
                    ],
                    'OPTIONS'
                ),
                $response = new DummyResponse(),
                new ConfigurationRegistry([
                    'test' => $hub2Config,
                ])
            ),
            'response' => $response,
            'expectedHeaders' => [],
        ];
    }

    /**
     * @dataProvider provideDiscoveries
     *
     * @param array<string,string> $expectedHeaders
     */
    public function testDiscoverySetFromRequest(
        Discovery $discovery,
        DummyResponse $response,
        array $expectedHeaders,
    ): void {
        $discovery->addLinkFromCurrentRequest();
        Assert::same($expectedHeaders, $response->getHeaders());
    }
}

(new DiscoveryTest())->run();
