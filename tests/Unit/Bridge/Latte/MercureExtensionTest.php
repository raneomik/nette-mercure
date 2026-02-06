<?php

declare(strict_types=1);

namespace Tests\Unit\Raneomik\NetteMercure\Bridge\Latte;

require dirname(__DIR__, 3) . '/bootstrap.php';

use Raneomik\NetteMercure\Bridge\Latte\MercureExtension;
use Raneomik\NetteMercure\Bridge\Utils\BroadcastersLoader;
use Raneomik\NetteMercure\Core\Broadcasters;
use Raneomik\NetteMercure\Core\PlainBroadcaster;
use Symfony\Component\Mercure\Jwt\StaticTokenProvider;
use Symfony\Component\Mercure\MockHub;
use Symfony\Component\Mercure\Update;
use Tester\Assert;
use Tester\TestCase;
use Tests\Fixtures\Dummies\DummyBroadcastContext;
use Tests\Fixtures\Dummies\DummyJwtProvider;

class MercureExtensionTest extends TestCase
{
    /**
     * @testCase
     */
    public function testExtensionFunctions(): void
	{
        $broadcasters = new Broadcasters([
            'test' => new PlainBroadcaster(
                new MockHub(
                    'http://hub.example.com',
                    new StaticTokenProvider('!ChangeMe1!'),
                    fn(Update $update): string => $update->getData(),
                ),
                new DummyBroadcastContext(),
            ),
        ]);

		$extension = new MercureExtension(
		    new DummyJwtProvider(),
		    new BroadcastersLoader(fn(): \Raneomik\NetteMercure\Core\Broadcasters => $broadcasters),
		);

		Assert::type('callable', $extension->getFunctions()['mercure']);
		Assert::same(
		    'http://hub.example.com?topic=test',
		    $extension->getFunctions()['mercure']('test', 'test'),
		);
		Assert::same(
		    'http://hub.example.com?topic=test&lastEventID=123&authorization=dummy-jwt-token-test-provider-token-*-*',
		    $extension->getFunctions()['mercure']('test', options: [
		        'lastEventId' => '123',
		        'addJwt' => true,
		    ]),
		);
		Assert::type('callable', $extension->getFunctions()['mercureJWTToken']);
        Assert::same(
            'dummy-jwt-token-test-provider-token-*-*',
            $extension->getFunctions()['mercureJWTToken']('test'),
        );
	}
}

(new MercureExtensionTest())->run();

