<?php

declare(strict_types=1);

namespace Tests\Unit\Raneomik\NetteMercure\Core\Subscribe;

require \dirname(__DIR__, 3).'/bootstrap.php';

use Raneomik\NetteMercure\Core\Subscribe\JWTProvider;
use Symfony\Component\Mercure\HubRegistry;
use Tester\Assert;
use Tester\TestCase;
use Tests\Fixtures\Dummies\Core\MockHubFactory;

/**
 * @testCase
 */
final class JWTProviderTest extends TestCase
{
    public function testMissingJWTFactoryException(): void
    {
        $jwtProvider = new JWTProvider(
            new HubRegistry(
                $defaultHub = MockHubFactory::create('http://hub.example.com', jwtToken: '?token?', withoutJWTFactory: true),
                [
                    'h1' => $defaultHub,
                    'h2' => MockHubFactory::create('http://hub2.example.com', jwtToken: '?token2?', withoutJWTFactory: true),
                ]
            ),
        );

        Assert::exception(
            static fn (): string => $jwtProvider->provide(
                subscribedTopics: 'test',
            ),
            \LogicException::class,
            'The default hub does not contain a token factory.',
        );

        Assert::exception(
            static fn (): string => $jwtProvider->provide(
                hubName: 'h2',
                subscribedTopics: 'test',
            ),
            \LogicException::class,
            'The "h2" hub does not contain a token factory.',
        );
    }

    public function testMinimalisticProvision(): void
    {
        $jwtProvider = new JWTProvider(
            new HubRegistry(
                MockHubFactory::create('http://hub.example.com', jwtSecret: 'secret?', jwtToken: '?token?'),
            ),
        );

        Assert::same(
            'dummy-jwt-token-secret?-test-',
            $jwtProvider->provide(
                subscribedTopics: 'test',
            ),
        );
        Assert::same(
            (new \DateTime('+1hour'))->format('Y-m-d H:i:s'),
            $jwtProvider->ttl()
                ->format('Y-m-d H:i:s'),
        );
    }

    public function testCustomCookieLifetime(): void
    {
        $jwtProvider = new JWTProvider(
            new HubRegistry(
                MockHubFactory::create('http://hub.example.com', jwtSecret: 'secret?'),
            ),
        );

        Assert::same(
            'dummy-jwt-token-secret?-test-',
            $jwtProvider->provide(
                subscribedTopics: 'test',
                additionalClaims: [
                    'exp' => 1800,
                ],
            ),
        );
        Assert::same(
            (new \DateTime('+ 30 minutes'))->format('Y-m-d H:i:s'),
            $jwtProvider->ttl()
                ->format('Y-m-d H:i:s'),
        );
    }

    public function testMultipleProvisions(): void
    {
        $jwtProvider = new JWTProvider(
            new HubRegistry(
                $defaultHub = MockHubFactory::create('http://hub.example.com', jwtSecret: 'secret?'),
                [
                    'h1' => $defaultHub,
                    'h2' => MockHubFactory::create('http://hub2.example.com', jwtSecret: 'secret2?'),
                ]
            ),
        );

        Assert::same(
            'dummy-jwt-token-secret?-test-',
            $jwtProvider->provide(
                subscribedTopics: 'test',
            ),
        );
        Assert::same(
            (new \DateTime('+1hour'))->format('Y-m-d H:i:s'),
            $jwtProvider->ttl()
                ->format('Y-m-d H:i:s'),
        );

        Assert::same(
            'dummy-jwt-token-secret2?-test-',
            $jwtProvider->provide(
                hubName: 'h2',
                subscribedTopics: 'test',
            ),
        );
        Assert::same(
            (new \DateTime('+1hour'))->format('Y-m-d H:i:s'),
            $jwtProvider->ttl()
                ->format('Y-m-d H:i:s'),
        );
    }
}

(new JWTProviderTest())->run();
