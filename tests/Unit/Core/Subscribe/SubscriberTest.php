<?php

declare(strict_types=1);

namespace Tests\Unit\Raneomik\NetteMercure\Core\Subscribe;

require \dirname(__DIR__, 3).'/bootstrap.php';

use Raneomik\NetteMercure\Bridge\DI\Config\ConfiguredData;
use Raneomik\NetteMercure\Bridge\DI\Config\ConfiguredDataRegistry;
use Raneomik\NetteMercure\Core\Subscribe\Subscriber;
use Tester\Assert;
use Tester\TestCase;
use Tests\Fixtures\Dummies\Core\Subscribe\DummyJwtProvider;

/**
 * @testCase
 */
final class SubscriberTest extends TestCase
{
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

        Assert::same('dummy-jwt-token-test-provider-token-test-', $jwtData->jwtToken);
        Assert::same('http://hub.example.com', $jwtData->hubUrl);
        Assert::type(\DateTimeInterface::class, $jwtData->expiresAt);

        $jwtData = $subscriber->subscribe();
        Assert::same('dummy-jwt-token-test-provider-token-*-', $jwtData->jwtToken);
    }
}

(new SubscriberTest())->run();
