<?php

declare(strict_types=1);

namespace Tests\Unit\Raneomik\NetteMercure\Core;

require \dirname(__DIR__, 3).'/bootstrap.php';

use Raneomik\NetteMercure\Bridge\Utils\ConfiguredData;
use Raneomik\NetteMercure\Bridge\Utils\ConfiguredDataRegistry;
use Raneomik\NetteMercure\Core\Discovery;
use Raneomik\NetteMercure\Core\Subscribe\Subscriber;
use Symfony\Component\WebLink\HttpHeaderSerializer;
use Tester\Assert;
use Tester\TestCase;
use Tests\Fixtures\Dummies\Core\DummyRequest;
use Tests\Fixtures\Dummies\Core\DummyResponse;
use Tests\Fixtures\Dummies\Core\Subscribe\DummyAuthorisation;
use Tests\Fixtures\Dummies\Core\Subscribe\DummyJwtProvider;

final class SubscriberTest extends TestCase
{
    /**
     * @testCase
     */
    public function testDefaults(): void
    {
        $discovery = new Discovery(
            new HttpHeaderSerializer(),
            new DummyRequest(),
            $response = new DummyResponse(),
        );

        $subscriber = new Subscriber(
            new DummyAuthorisation(),
            new DummyJwtProvider(),
            $discovery,
            new ConfiguredDataRegistry([
                'test' => new ConfiguredData(
                    hubName: 'test',
                    hubUrl: 'http://hub.example.com',
                    subscribe: ['*'],
                    publish: ['*'],
                    disableCookie: false,
                ),
            ])
        );

        $jwtData = $subscriber->subscribe(topics: 'test');

        Assert::contains('http://hub.example.com', $response->getHeader('Link') ?? '');

        Assert::contains('dummy-jwt-token', $jwtData->jwtToken);
        Assert::same('http://hub.example.com', $jwtData->hubUrl);
        Assert::type(\DateTimeInterface::class, $jwtData->expiresAt);
    }
}

(new SubscriberTest())->run();
