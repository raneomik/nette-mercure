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

final class MercureExtensionTest extends TestCase
{
    /**
     * @testCase
     */
    public function testExtensionFunctions(): void
    {
        $broadcasters = new Broadcasters([
            'test' => new PlainBroadcaster(
                MockHubFactory::create('http://hub.example.com'),
            ),
        ]);

        $extension = new MercureExtension(
            new DummyJwtProvider(),
            new BroadcastersLoader(static fn (): Broadcasters => $broadcasters),
            new ConfiguredDataRegistry([
                'test' => new ConfiguredData(
                    'test',
                    'http://hub.example.com',
                    ['*'],
                    ['*'],
                    false,
                ),
            ]),
        );

        Assert::type('callable', $extension->getFunctions()['mercure']);
        Assert::same(
            'http://hub.example.com?topic=test',
            $extension->getFunctions()['mercure']('test', 'test'),
        );
        Assert::same(
            'http://hub.example.com?topic=test&lastEventID=123&authorization=dummy-jwt-token-test-provider-token-*-*',
            $extension->getFunctions()['mercure']('test', hub: 'test', options: [
                'lastEventId' => '123',
                'addJwt' => true,
            ]),
        );

        Assert::type('callable', $extension->getFunctions()['mercureJWTToken']);
        Assert::same(
            'dummy-jwt-token-test-provider-token-*-*',
            $extension->getFunctions()['mercureJWTToken'](),
        );
    }
}

(new MercureExtensionTest())->run();
