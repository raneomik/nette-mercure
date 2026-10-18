<?php

declare(strict_types=1);

namespace Tests\Integration\Raneomik\NetteMercure\Bridge\DI;

require \dirname(__DIR__, 3) . '/bootstrap.php';

use Nette\DI\Container;
use Nette\DI\InvalidConfigurationException;
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
     * @return iterable<string, array{string, ?class-string<TokenFactoryInterface>, class-string<TokenFactoryInterface>, callable(array<string, mixed>): void}>
     */
    public function provideRealTokenFactories(): iterable
    {
        $assertV1 = static function (array $payload): void {
            Assert::hasNotKey('mercure', $payload);
            Assert::same('https://app.example.com', $payload['iss']);
            Assert::same('https://localhost/.well-known/mercure', $payload['aud']);
            Assert::same('https://localhost', $payload['sub']);
            Assert::same('https://localhost', $payload['client_id']);
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

        yield 'v1 default factory' => ['1.0', null, WebTokenFactory::class, $assertV1];
        yield 'v1 lcobucci' => ['1.0', LcobucciFactory::class, LcobucciFactory::class, $assertV1];
        yield 'legacy default factory' => [
            '0.x',
            null,
            LcobucciFactory::class,
            static function (array $payload): void {
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
                secret: n3tt3-m3rcur3-t3st-jwt-s3cr3t-k3y-32
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
                    secret: jwt-secret
                    factory: {$webTokenFactory}
            "),
            InvalidConfigurationException::class,
            '~only supports the Mercure protocol 1.0~',
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeJwtPayload(string $jwt): array
    {
        $payload = explode('.', $jwt)[1] ?? '';

        /** @var array<string, mixed> */
        return json_decode(base64_decode(strtr($payload, '-_', '+/'), true) ?: '', true, flags: \JSON_THROW_ON_ERROR);
    }
}

(new TokenFactoryTest())->run();
