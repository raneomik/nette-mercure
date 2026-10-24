<?php

declare(strict_types=1);

namespace Tests\Unit\Raneomik\NetteMercure\Bridge\Utils;

require \dirname(__DIR__, 3) . '/bootstrap.php';

use Raneomik\NetteMercure\Bridge\DI\Config\SigningKey;
use Raneomik\NetteMercure\Bridge\Utils\TokenFactoryFactory;
use Symfony\Component\Mercure\Jwt\LcobucciFactory;
use Symfony\Component\Mercure\Jwt\TokenFactoryInterface;
use Symfony\Component\Mercure\Jwt\WebTokenFactory;
use Symfony\Component\Mercure\ProtocolVersion;
use Tester\Assert;
use Tester\TestCase;
use Tests\Fixtures\Dummies\Core\DummyJwtFactory;

/**
 * @testCase
 */
final class TokenFactoryFactoryTest extends TestCase
{
    private const string SECRET = 'n3tt3-m3rcur3-t3st-jwt-s3cr3t-k3y-48-n3tt3-m3rcur3';

    /**
     * @return iterable<string, array{
     *     factory: class-string<TokenFactoryInterface>,
     *     protocolVersion: ProtocolVersion,
     *     algorithm: ?string,
     *     expectedAlgorithm: string,
     * }>
     */
    public function provideFactories(): iterable
    {
        yield 'web-token default algorithm' => [
            'factory' => WebTokenFactory::class,
            'protocolVersion' => ProtocolVersion::V1,
            'algorithm' => null,
            'expectedAlgorithm' => 'HS256',
        ];

        yield 'web-token custom algorithm' => [
            'factory' => WebTokenFactory::class,
            'protocolVersion' => ProtocolVersion::V1,
            'algorithm' => 'HS384',
            'expectedAlgorithm' => 'HS384',
        ];

        yield 'lcobucci default algorithm' => [
            'factory' => LcobucciFactory::class,
            'protocolVersion' => ProtocolVersion::Legacy,
            'algorithm' => null,
            'expectedAlgorithm' => 'HS256',
        ];

        yield 'lcobucci custom algorithm' => [
            'factory' => LcobucciFactory::class,
            'protocolVersion' => ProtocolVersion::V1,
            'algorithm' => 'hmac.sha384',
            'expectedAlgorithm' => 'HS384',
        ];
    }

    /**
     * @dataProvider provideFactories
     *
     * @param class-string<TokenFactoryInterface> $factory
     */
    public function testFactories(
        string $factory,
        ProtocolVersion $protocolVersion,
        ?string $algorithm,
        string $expectedAlgorithm,
    ): void {
        $tokenFactory = TokenFactoryFactory::create(
            new SigningKey(self::SECRET, algorithm: $algorithm),
            $factory,
            3600,
            $protocolVersion,
        );

        Assert::type($factory, $tokenFactory);

        $jwt = $tokenFactory->create(additionalClaims: [
            'iss' => 'iss',
            'aud' => 'aud',
            'sub' => 'sub',
            'client_id' => 'client',
        ]);

        /** @var array{alg: string} $header */
        $header = json_decode(base64_decode(strtr(explode('.', $jwt)[0], '-_', '+/'), true) ?: '', true, flags: \JSON_THROW_ON_ERROR);
        Assert::same($expectedAlgorithm, $header['alg']);
    }

    public function testCustomFactoryReceivesTheSecret(): void
    {
        $tokenFactory = TokenFactoryFactory::create(
            new SigningKey('custom-secret'),
            DummyJwtFactory::class,
            null,
            ProtocolVersion::V1,
        );

        Assert::type(DummyJwtFactory::class, $tokenFactory);
        Assert::contains('custom-secret', $tokenFactory->create());
    }

    public function testEmptySecretIsRejected(): void
    {
        Assert::exception(
            static fn (): SigningKey => new SigningKey(''),
            \InvalidArgumentException::class,
            'The JWT signing key secret must not be empty.',
        );
    }
}

(new TokenFactoryFactoryTest())->run();
