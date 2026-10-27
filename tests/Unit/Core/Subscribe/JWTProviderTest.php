<?php

declare(strict_types=1);

namespace Tests\Unit\Raneomik\NetteMercure\Core\Subscribe;

require \dirname(__DIR__, 3) . '/bootstrap.php';

use Nette\Utils\Json;
use Raneomik\NetteMercure\Bridge\DI\Config\Configuration;
use Raneomik\NetteMercure\Bridge\DI\Config\ConfigurationRegistry;
use Raneomik\NetteMercure\Core\Subscribe\JWTProvider;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\HubRegistry;
use Symfony\Component\Mercure\Jwt\Grant;
use Symfony\Component\Mercure\Jwt\StaticTokenProvider;
use Symfony\Component\Mercure\Jwt\WebTokenFactory;
use Symfony\Component\Mercure\MockHub;
use Tester\Assert;
use Tester\TestCase;
use Tests\Fixtures\Dummies\Core\MockHubFactory;

/**
 * @testCase
 */
final class JWTProviderTest extends TestCase
{
    private static HubInterface $defaultHub;

    protected function setUp(): void
    {
        self::$defaultHub ??= MockHubFactory::create('http://hub.example.com', jwtToken: '?token?');
    }

    public function testMissingJWTFactoryException(): void
    {
        $jwtProvider = $this->provider(new HubRegistry(
            $defaultHub = MockHubFactory::create('http://hub.example.com', jwtToken: '?token?', withoutJWTFactory: true),
            [
                'h1' => $defaultHub,
                'h2' => MockHubFactory::create('http://hub2.example.com', jwtToken: '?token2?', withoutJWTFactory: true),
            ]
        ));

        Assert::exception(
            static fn (): \Raneomik\NetteMercure\Core\Subscribe\Value\JWTToken => $jwtProvider->provide(
                subscribedTopics: 'test',
            ),
            \LogicException::class,
            'The default hub does not contain a token factory.',
        );

        Assert::exception(
            static fn (): \Raneomik\NetteMercure\Core\Subscribe\Value\JWTToken => $jwtProvider->provide(
                hubName: 'h2',
                subscribedTopics: 'test',
            ),
            \LogicException::class,
            'The "h2" hub does not contain a token factory.',
        );
    }

    public function testMultipleProvisions(): void
    {
        $jwtProvider = $this->provider(new HubRegistry(
            self::$defaultHub,
            [
                'h1' => self::$defaultHub,
                'h2' => MockHubFactory::create('http://hub2.example.com', jwtSecret: 'secret2?'),
            ]
        ));

        $result = $jwtProvider->provide(subscribedTopics: 'test');
        Assert::same('dummy-jwt-token-secret-test-', $result->token);
        Assert::same((new \DateTime('+1hour'))->format('Y-m-d H:i:s'), $result->ttl?->format('Y-m-d H:i:s'));

        $result = $jwtProvider->provide(hubName: 'h2', subscribedTopics: 'test');
        Assert::same('dummy-jwt-token-secret2?-test-', $result->token);
        Assert::same((new \DateTime('+1hour'))->format('Y-m-d H:i:s'), $result->ttl?->format('Y-m-d H:i:s'));
    }

    /**
     * @return iterable<string, array{
     *     grants: mixed,
     *     expected: string,
     * }>
     */
    public function provideGrants(): iterable
    {
        yield 'no grant' => [
            'grants' => [],
            'expected' => 'dummy-jwt-token-secret--',
        ];

        yield 'topic' => [
            'grants' => 'test',
            'expected' => 'dummy-jwt-token-secret-test-',
        ];

        yield 'matcher map' => [
            'grants' => [
                'urlpattern' => 'test',
            ],
            'expected' => 'dummy-jwt-token-secret-test-',
        ];

        yield 'Grant' => [
            'grants' => [
                new Grant([Grant::ACTION_SUBSCRIBE], [
                    'urlpattern' => 'test',
                ]),
            ],
            'expected' => 'dummy-jwt-token-secret-test-',
        ];

        yield 'Grant-shaped array' => [
            'grants' => [
                [
                    'topics' => [
                        'urlpattern' => 'test',
                    ],
                ],
            ],
            'expected' => 'dummy-jwt-token-secret-test-',
        ];
    }

    /**
     * @dataProvider provideGrants
     *
     * @param array<mixed>|string $grants
     */
    public function testGrants(array|string $grants, string $expected): void
    {
        Assert::same($expected, $this->provider(new HubRegistry(self::$defaultHub))->provide(subscribedTopics: $grants)->token);
    }

    /**
     * @return iterable<string, array{
     *     lifetime: ?int,
     *     claims: array<string, mixed>,
     *     expectedSeconds: ?int,
     * }>
     */
    public function provideLifetimes(): iterable
    {
        yield 'default lifetime' => [
            'lifetime' => 0,
            'claims' => [],
            'expectedSeconds' => 3600,
        ];

        yield 'configured lifetime' => [
            'lifetime' => 600,
            'claims' => [],
            'expectedSeconds' => 600,
        ];

        yield 'non-expiring' => [
            'lifetime' => null,
            'claims' => [],
            'expectedSeconds' => null,
        ];

        yield '"exp" in seconds' => [
            'lifetime' => 0,
            'claims' => [
                'exp' => 1800,
            ],
            'expectedSeconds' => 1800,
        ];

        yield '"exp" as a string' => [
            'lifetime' => 0,
            'claims' => [
                'exp' => '1800',
            ],
            'expectedSeconds' => 1800,
        ];

        yield '"exp" over a non-expiring config' => [
            'lifetime' => null,
            'claims' => [
                'exp' => 300,
            ],
            'expectedSeconds' => 300,
        ];

        yield '"exp" as a date' => [
            'lifetime' => 0,
            'claims' => [
                'exp' => new \DateTimeImmutable('+300 seconds'),
            ],
            'expectedSeconds' => 300,
        ];
    }

    /**
     * The token and the cookie must expire together.
     *
     * @dataProvider provideLifetimes
     *
     * @param array<string, mixed> $claims
     */
    public function testLifetimeInTheRealToken(?int $lifetime, array $claims, ?int $expectedSeconds): void
    {
        $jwtProvider = $this->provider($this->realHubRegistry(), $lifetime);
        $result = $jwtProvider->provide(subscribedTopics: 'test', additionalClaims: $claims + [
            'iss' => 'i',
            'aud' => 'a',
            'sub' => 's',
            'client_id' => 'c',
        ]);
        $payload = $this->payload($result->token);

        if (null === $expectedSeconds) {
            Assert::false(isset($payload['exp']));
            Assert::null($result->ttl);

            return;
        }

        Assert::true(2 >= abs(time() + $expectedSeconds - $payload['exp']));
        Assert::same($payload['exp'], $result->ttl?->getTimestamp());
    }

    private function provider(HubRegistry $registry, ?int $lifetime = 0): JWTProvider
    {
        return new JWTProvider($registry, new ConfigurationRegistry([
            'default' => new Configuration('default', 'https://hub', 'https://hub', ['*'], ['*'], lifetime: $lifetime),
        ]));
    }

    private function realHubRegistry(): HubRegistry
    {
        return new HubRegistry(new MockHub(
            'https://hub.example.com',
            new StaticTokenProvider('unused'),
            static fn (): string => 'id',
            WebTokenFactory::fromSecret(str_repeat('s', 32), jwtLifetime: null),
        ));
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(string $token): array
    {
        /** @var array<string, mixed> */
        return Json::decode(base64_decode(strtr(explode('.', $token)[1], '-_', '+/'), true) ?: '', forceArrays: true);
    }
}

(new JWTProviderTest())->run();
