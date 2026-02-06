<?php

declare(strict_types=1);

namespace Tests\Unit\Raneomik\NetteMercure\Core\Response;

require __DIR__ . '/../../../bootstrap.php';

use Nette\Http\IResponse;
use Raneomik\NetteMercure\Bridge\Utils\DefinedData;
use Raneomik\NetteMercure\Core\Response\BroadcastContext;
use Raneomik\NetteMercure\Core\Response\Discovery;
use Symfony\Component\WebLink\HttpHeaderSerializer;
use Tester\Assert;
use Tester\TestCase;
use Tests\Fixtures\Dummies\DummyAuthorisation;
use Tests\Fixtures\Dummies\DummyRequest;
use Tests\Fixtures\Dummies\DummyResponse;

class BroadcastContextTest extends TestCase
{
    private Discovery $discovery;

    private DummyResponse $response;

    protected function setUp(): void
    {
        $this->discovery = new Discovery(
            new HttpHeaderSerializer(),
            new DummyRequest(),
            $this->response = new DummyResponse(),
        );
    }

	/**
	 * @testCase
	 */
	public function testDefinedDataWithCookie(): void
	{
        $broadcastContext = new BroadcastContext(
            new DummyAuthorisation($this->response),
            $this->discovery,
            [
                'test' => new DefinedData(
                    $hubUrl = 'http://hub.example.com',
                    ['subscribe'],
                    ['publish'],
                    false,
                ),
            ]
        );

        $broadcastContext->setHubContextData($hubUrl);

        $broadcastContext->addResponseLinks();
        $broadcastContext->createCookies();

        Assert::same(
            '<http://hub.example.com>; rel="mercure"',
            $this->response->getHeader('Link'),
        );
        Assert::same(
            [
                'value' => 'cookie-value-subscribe-publish-test',
                'expire' => 0,
                'path' => null,
                'domain' => null,
                'secure' => null,
                'httpOnly' => null,
                'sameSite' => IResponse::SameSiteLax,
            ],
            $this->response->cookie['cookie'],
        );
	}

	/**
	 * @testCase
	 */
	public function testDefinedDataWithoutCookie(): void
	{
        $broadcastContext = new BroadcastContext(
            new DummyAuthorisation($this->response),
            $this->discovery,
            [
                'test' => new DefinedData(
                    $hubUrl = 'http://hub.example.com',
                    ['subscribe'],
                    ['publish'],
                    true,
                ),
            ]
        );

        $broadcastContext->setHubContextData($hubUrl);

        $broadcastContext->addResponseLinks();
        $broadcastContext->createCookies();

        Assert::same(
            '<http://hub.example.com>; rel="mercure"',
            $this->response->getHeader('Link'),
        );
        Assert::count(0, $this->response->cookie);
	}
}

(new BroadcastContextTest())->run();
