<?php

declare(strict_types=1);

namespace Tests\Unit\Raneomik\NetteMercure\Core;

require \dirname(__DIR__, 2).'/bootstrap.php';

use Raneomik\NetteMercure\Bridge\Utils\ConfiguredData;
use Raneomik\NetteMercure\Bridge\Utils\ConfiguredDataRegistry;
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
    public function testNoLinkAddition(): void
    {
        $discovery = new Discovery(
            new HttpHeaderSerializer(),
            new DummyRequest(
                [
                    'Access-Control-Request-Method' => 'get',
                ],
                'OPTIONS'
            ),
            $response = new DummyResponse(),
            new ConfiguredDataRegistry([
                'test' => new ConfiguredData(
                    hubName: 'test',
                    hubUrl: 'http://hub.example.com',
                    subscribe: ['*'],
                    publish: ['*'],
                ),
            ])
        );

        $discovery->addLink('http://example.com/hub');

        Assert::null($response->getHeader('Link'));
    }

    public function testMinimalisticBroadcast(): void
    {
        $discovery = new Discovery(
            new HttpHeaderSerializer(),
            new DummyRequest(),
            $response = new DummyResponse(),
            new ConfiguredDataRegistry([
                'test' => new ConfiguredData(
                    hubName: 'test',
                    hubUrl: 'http://hub.example.com',
                    subscribe: ['*'],
                    publish: ['*'],
                ),
            ])
        );

        $discovery->addLink('http://example.com/hub');
        Assert::Same(
            '<http://example.com/hub>; rel="mercure"',
            $response->getHeader('Link'),
        );
    }

    public function testDiscoverySetFromRequest(): void
    {
        $discovery = new Discovery(
            new HttpHeaderSerializer(),
            new DummyRequest(fromUrl: '/?hubName=test2'),
            $response = new DummyResponse(),
            new ConfiguredDataRegistry([
                'test' => new ConfiguredData(
                    hubName: 'test',
                    hubUrl: 'http://hub.example.com',
                    subscribe: ['*'],
                    publish: ['*'],
                ),
                'test2' => new ConfiguredData(
                    hubName: 'test',
                    hubUrl: 'http://hub2.example.com',
                    subscribe: ['*'],
                    publish: ['*'],
                    autoDiscovery: true,
                ),
            ])
        );

        $discovery->addLinkFromCurrentRequest();
        Assert::Same(
            '<http://hub2.example.com>; rel="mercure"',
            $response->getHeader('Link'),
        );

        $discovery = new Discovery(
            new HttpHeaderSerializer(),
            new DummyRequest(fromUrl: '/?hubName=test2'),
            $response = new DummyResponse(),
            new ConfiguredDataRegistry([
                'test' => new ConfiguredData(
                    hubName: 'test',
                    hubUrl: 'http://hub.example.com',
                    subscribe: ['*'],
                    publish: ['*'],
                ),
            ])
        );

        $discovery->addLinkFromCurrentRequest();
        Assert::hasNotKey('Link', $response->getHeaders());
    }
}

(new DiscoveryTest())->run();
