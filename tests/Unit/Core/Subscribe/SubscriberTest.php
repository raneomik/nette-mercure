<?php

declare(strict_types=1);

namespace Tests\Unit\Raneomik\NetteMercure\Core;

require \dirname(__DIR__, 3).'/bootstrap.php';

use Raneomik\NetteMercure\Bridge\Utils\ConfiguredData;
use Raneomik\NetteMercure\Bridge\Utils\ConfiguredDataRegistry;
use Raneomik\NetteMercure\Core\Subscribe\Subscriber;
use Tester\Assert;
use Tester\TestCase;
use Tests\Fixtures\Dummies\Core\Subscribe\DummyJwtProvider;

final class SubscriberTest extends TestCase
{
    /**
     * @testCase
     */
    public function testDefaults(): void
    {
        $subscriber = new Subscriber(
            new DummyJwtProvider(),
            new ConfiguredDataRegistry([
                'test' => new ConfiguredData(
                    hubName: 'test',
                    hubUrl: 'http://hub.example.com',
                    subscribe: ['*'],
                    publish: ['*'],
                ),
            ])
        );

        $jwtData = $subscriber->subscribe(topics: 'test');

        Assert::contains('dummy-jwt-token', $jwtData->jwtToken);
        Assert::same('http://hub.example.com', $jwtData->hubUrl);
        Assert::type(\DateTimeInterface::class, $jwtData->expiresAt);
    }
}

(new SubscriberTest())->run();
