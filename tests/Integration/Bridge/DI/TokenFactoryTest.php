<?php

declare(strict_types=1);

namespace Tests\Integration\Raneomik\NetteMercure\Bridge\DI;

require \dirname(__DIR__, 3) . '/bootstrap.php';

use Nette\DI\Container;
use Nette\DI\InvalidConfigurationException;
use Raneomik\NetteMercure\Bridge\DI\Config\Claims;
use Raneomik\NetteMercure\Bridge\DI\Config\ConfigurationRegistry;
use Symfony\Component\Mercure\Hub;
use Symfony\Component\Mercure\Jwt\LcobucciFactory;
use Symfony\Component\Mercure\Jwt\TokenFactoryInterface;
use Symfony\Component\Mercure\Jwt\WebTokenFactory;
use Tester\Assert;

/**
 * @testCase
 */
final class TokenFactoryTest extends ContainerTestCase
{
    /**
     * @return iterable<string, array{
     *     protocolVersion: string,
     *     factory: ?class-string<TokenFactoryInterface>,
     *     expectedFactory: class-string<TokenFactoryInterface>,
     *     assertPayload: callable(array<string, mixed>): void,
     * }>
     */
    public function provideRealTokenFactories(): iterable
    {
        $assertV1 = static function (array $payload): void {
            Assert::hasNotKey('mercure', $payload);
            Assert::same('https://app.example.com', $payload['iss']);
            Assert::same('https://hub.example.com/.well-known/mercure', $payload['aud']);
            Assert::same('https://hub.example.com', $payload['sub']);
            Assert::same('https://hub.example.com', $payload['client_id']);
            Assert::same([
                [
                    'type' => 'https://mercure.rocks/authorization-detail',
                    'actions' => ['subscribe'],
                    'topics' => [[
                        'match' => 'books',
                    ]],
                ],
                [
                    'type' => 'https://mercure.rocks/authorization-detail',
                    'actions' => ['publish'],
                    'topics' => [[
                        'match' => 'books',
                    ], [
                        'match' => 'authors',
                    ]],
                ],
            ], $payload['authorization_details']);
        };

        yield 'v1 default factory' => [
            'protocolVersion' => '1.0',
            'factory' => null,
            'expectedFactory' => WebTokenFactory::class,
            'assertPayload' => $assertV1,
        ];

        yield 'v1 lcobucci' => [
            'protocolVersion' => '1.0',
            'factory' => LcobucciFactory::class,
            'expectedFactory' => LcobucciFactory::class,
            'assertPayload' => $assertV1,
        ];

        yield 'legacy default factory' => [
            'protocolVersion' => '0.x',
            'factory' => null,
            'expectedFactory' => LcobucciFactory::class,
            'assertPayload' => static function (array $payload): void {
                Assert::hasNotKey('authorization_details', $payload);
                Assert::hasNotKey('aud', $payload);
                Assert::same('https://app.example.com', $payload['iss']);
                Assert::equal([
                    'publish' => ['books', 'authors'],
                    'subscribe' => ['books'],
                ], $payload['mercure']);
            },
        ];
    }

    /**
     * @dataProvider provideRealTokenFactories
     *
     * @param ?class-string<TokenFactoryInterface> $factory
     * @param class-string<TokenFactoryInterface> $expectedFactory
     * @param callable(array<string, mixed>): void $assertPayload
     */
    public function testRealTokenFactories(
        string $protocolVersion,
        ?string $factory,
        string $expectedFactory,
        callable $assertPayload,
    ): void {
        $container = $this->createContainer('
        mercure:
            url: https://hub.example.com/.well-known/mercure
            protocolVersion: "' . $protocolVersion . '"
            jwt:
                key: n3tt3-m3rcur3-t3st-jwt-s3cr3t-k3y-32
                ' . (null === $factory ? '' : 'factory: ' . $factory) . '
                subscribe: [books]
                publish: [books, authors]
                claims:
                    iss: https://app.example.com
        ');

        Assert::type($expectedFactory, $container->getService('mercure.token.factory.decorated.default'));

        /** @var Hub $hub */
        $hub = $container->getService('mercure.sf.hub.default');
        $assertPayload($this->decodeJwtPayload($hub->getProvider()->getJwt()));
    }

    public function testWebTokenFactoryRejectsLegacyProtocol(): void
    {
        $webTokenFactory = WebTokenFactory::class;

        Assert::exception(
            fn (): Container => $this->createContainer("
            mercure:
                protocolVersion: '0.x'
                jwt:
                    key: jwt-secret
                    factory: {$webTokenFactory}
            "),
            InvalidConfigurationException::class,
            '~only supports the Mercure protocol 1.0~',
        );
    }

    public function testFullSigningKeyAndClaimsConfiguration(): void
    {
        $container = $this->createContainer('
        mercure:
            url: https://hub.example.com/.well-known/mercure
            jwt:
                key:
                    secret: n3tt3-m3rcur3-t3st-jwt-s3cr3t-k3y-48-n3tt3-m3rcur3
                    algorithm: HS384
                claims:
                    iss: https://app.example.com
                    aud: https://hub.example.com/.well-known/mercure
                    sub: user-42
                    clientId: nette-app
                    additional:
                        tenant: acme
                        sub: overridden-by-named-claim
        ');

        /** @var Hub $hub */
        $hub = $container->getService('mercure.sf.hub.default');
        $jwt = $hub->getProvider()
            ->getJwt();

        Assert::same('HS384', $this->decodeJwtPart($jwt, 0)['alg']);

        $payload = $this->decodeJwtPart($jwt, 1);
        Assert::same('https://app.example.com', $payload['iss']);
        Assert::same('https://hub.example.com/.well-known/mercure', $payload['aud']);
        Assert::same('user-42', $payload['sub']);
        Assert::same('nette-app', $payload['client_id']);
        Assert::same('acme', $payload['tenant']);
        Assert::hasNotKey('secret', $payload);

        /** @var ConfigurationRegistry $registry */
        $registry = $container->getService('mercure.hubsConfiguration');
        Assert::equal(new Claims(
            iss: 'https://app.example.com',
            aud: 'https://hub.example.com/.well-known/mercure',
            sub: 'user-42',
            clientId: 'nette-app',
            additional: [
                'tenant' => 'acme',
                'sub' => 'overridden-by-named-claim',
            ],
        ), $registry->getConfiguration()
            ->claims);
    }

    public function testEmptySecretIsRejected(): void
    {
        $container = $this->createContainer('
        mercure:
            jwt:
                key: ""
        ');

        Assert::exception(
            static fn (): object => $container->getService('mercure.token.factory.decorated.default'),
            \InvalidArgumentException::class,
            'The JWT signing key secret must not be empty.',
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeJwtPayload(string $jwt): array
    {
        return $this->decodeJwtPart($jwt, 1);
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeJwtPart(string $jwt, int $part): array
    {
        $encoded = explode('.', $jwt)[$part] ?? '';

        /** @var array<string, mixed> */
        return json_decode(base64_decode(strtr($encoded, '-_', '+/'), true) ?: '', true, flags: \JSON_THROW_ON_ERROR);
    }
}

(new TokenFactoryTest())->run();
