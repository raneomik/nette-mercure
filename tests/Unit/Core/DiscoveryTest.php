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
            new ConfigurationRegistry([
                'test' => new Configuration(
                    hubName: 'test',
                    hubUrl: 'http://hub.example.com',
                    publicUrl: 'http://hub.example.com',
                    subscribe: ['*'],
                    publish: ['*'],
                ),
            ])
        );

        $discovery->addLink('http://example.com/hub');

        Assert::null($response->getHeader('Link'));
    }

    public function testMinimalisticDefaultBroadcast(): void
    {
        $discovery = new Discovery(
            new HttpHeaderSerializer(),
            new DummyRequest(),
            $response = new DummyResponse(),
            new ConfigurationRegistry([
                'test' => new Configuration(
                    hubName: 'test',
                    hubUrl: 'http://hub.example.com',
                    publicUrl: 'http://hub.example.com',
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
            new ConfigurationRegistry([
                'test' => new Configuration(
                    hubName: 'test',
                    hubUrl: 'http://hub.example.com',
                    publicUrl: 'http://hub.example.com',
                    subscribe: ['*'],
                    publish: ['*'],
                ),
                'test2' => new Configuration(
                    hubName: 'test',
                    hubUrl: 'http://hub2.example.com',
                    publicUrl: 'http://hub2.example.com',
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
            new ConfigurationRegistry([
                'test' => new Configuration(
                    hubName: 'test',
                    hubUrl: 'http://hub.example.com',
                    publicUrl: 'http://hub.example.com',
                    subscribe: ['*'],
                    publish: ['*'],
                ),
            ])
        );

        $discovery->addLinkFromCurrentRequest();
        Assert::hasNotKey('Link', $response->getHeaders());

        $discovery = new Discovery(
            new HttpHeaderSerializer(),
            new DummyRequest(fromUrl: '/?hubName=test2&' . Discovery::NO_LINK_DISCOVER . '=1'),
            $response = new DummyResponse(),
            new ConfigurationRegistry([
                'test' => new Configuration(
                    hubName: 'test',
                    hubUrl: 'http://hub.example.com',
                    publicUrl: 'http://hub.example.com',
                    subscribe: ['*'],
                    publish: ['*'],
                    autoDiscovery: true,
                ),
            ])
        );
        $discovery = new Discovery(
            new HttpHeaderSerializer(),
            new DummyRequest([
                Discovery::NO_LINK_DISCOVER => '1',
            ], fromUrl: '/?hubName=test2&'),
            $response = new DummyResponse(),
            new ConfigurationRegistry([
                'test' => new Configuration(
                    hubName: 'test',
                    hubUrl: 'http://hub.example.com',
                    publicUrl: 'http://hub.example.com',
                    subscribe: ['*'],
                    publish: ['*'],
                    autoDiscovery: true,
                ),
            ])
        );

        $discovery->addLinkFromCurrentRequest();
        Assert::hasNotKey('Link', $response->getHeaders());
    }
}

(new DiscoveryTest())->run();
