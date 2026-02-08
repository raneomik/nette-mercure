<?php

declare(strict_types=1);

namespace Tests\Unit\Raneomik\NetteMercure\Core\Response;

require \dirname(__DIR__, 2).'/bootstrap.php';

use Raneomik\NetteMercure\Core\Discovery;
use Symfony\Component\WebLink\HttpHeaderSerializer;
use Tester\Assert;
use Tester\TestCase;
use Tests\Fixtures\Dummies\Core\DummyRequest;
use Tests\Fixtures\Dummies\Core\DummyResponse;

final class DiscoveryTest extends TestCase
{
    /**
     * @testCase
     */
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
        );

        $discovery->addLink('http://example.com/hub');

        Assert::null($response->getHeader('Link'));
    }

    /**
     * @testCase
     */
    public function testMinimalisticBroadcast(): void
    {
        $discovery = new Discovery(
            new HttpHeaderSerializer(),
            new DummyRequest(),
            $response = new DummyResponse(),
        );

        $discovery->addLink('http://example.com/hub');
        Assert::Same(
            '<http://example.com/hub>; rel="mercure"',
            $response->getHeader('Link'),
        );
    }
}

(new DiscoveryTest())->run();
