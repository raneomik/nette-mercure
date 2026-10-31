<?php

declare(strict_types=1);

namespace Tests\Unit\Raneomik\NetteMercure\Core\Subscribe;

require \dirname(__DIR__, 3) . '/bootstrap.php';

use Raneomik\NetteMercure\Bridge\DI\Config\Configuration;
use Raneomik\NetteMercure\Bridge\DI\Config\ConfigurationRegistry;
use Raneomik\NetteMercure\Core\Subscribe\JWTProviderInterface;
use Raneomik\NetteMercure\Core\Subscribe\Subscriber;
use Symfony\Component\Mercure\Exception\InvalidArgumentException;
use Symfony\Component\Mercure\Jwt\Grant;
use Symfony\Component\Mercure\ProtocolVersion;
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

        $jwtData = $subscriber->subscribe(topics: 'test');

        Assert::same('dummy-jwt-token-test-provider-token-test-', $jwtData->jwt->token);
        Assert::same('http://hub.example.com', $jwtData->hubUrl);
        Assert::same('http://hub.example.com?match=test', $jwtData->subscribeUrl);
        Assert::type(\DateTimeInterface::class, $jwtData->jwt->ttl);

        $jwtData = $subscriber->subscribe();
        Assert::same('dummy-jwt-token-test-provider-token-*-', $jwtData->jwt->token);
    }

    public function testRequestTopicsCannotGrantPublish(): void
    {
        $provider = new class() implements JWTProviderInterface {
            public mixed $grants = null;

            public function provide(?string $hubName = null, array|string $subscribedTopics = [], array $additionalClaims = []): \Raneomik\NetteMercure\Core\Subscribe\Value\JWTToken
            {
                $this->grants = $subscribedTopics;

                return new \Raneomik\NetteMercure\Core\Subscribe\Value\JWTToken('token', new \DateTimeImmutable());
            }
        };
        $subscriber = new Subscriber($provider, new ConfigurationRegistry([
            'test' => new Configuration('test', 'https://hub', 'https://hub', ['*'], ['*']),
        ]));

        parse_str('topics[0][actions][]=publish&topics[0][topics][]=*', $query);
        Assert::exception(
            static fn (): \Raneomik\NetteMercure\Core\Subscribe\Value\SubscriptionData => $subscriber->subscribe(null, $query['topics']),
            InvalidArgumentException::class,
        );
        Assert::null($provider->grants, 'no token for a Grant-shaped request');

        parse_str('topics[actions][]=publish', $query);
        $subscriber->subscribe(null, $query['topics']);

        Assert::count(1, $provider->grants);
        Assert::same([Grant::ACTION_SUBSCRIBE], $provider->grants[0]->actions);
    }

    public function testSubscribeUrl(): void
    {
        $provider = new class() implements JWTProviderInterface {
            public function provide(?string $hubName = null, array|string $subscribedTopics = [], array $additionalClaims = []): \Raneomik\NetteMercure\Core\Subscribe\Value\JWTToken
            {
                return new \Raneomik\NetteMercure\Core\Subscribe\Value\JWTToken('token', null);
            }
        };
        $subscriber = new Subscriber($provider, new ConfigurationRegistry([
            'v1' => new Configuration('v1', 'https://hub', 'https://hub', ['*'], ['*']),
            'legacy' => new Configuration('legacy', 'https://hub', 'https://hub', ['*'], ['*'], protocolVersion: ProtocolVersion::Legacy),
        ]));

        // the query itself is covered by SubscriptionQueryTest: only the hub and its protocol are picked here
        Assert::same('https://hub?topic=%2Fchats%2F1', $subscriber->subscribe('legacy', '/chats/1')->subscribeUrl);
    }
}

(new SubscriberTest())->run();
