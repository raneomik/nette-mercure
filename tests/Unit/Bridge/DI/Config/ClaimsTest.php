<?php

declare(strict_types=1);

namespace Tests\Unit\Raneomik\NetteMercure\Bridge\DI\Config;

require \dirname(__DIR__, 4) . '/bootstrap.php';

use Raneomik\NetteMercure\Bridge\DI\Config\Claims;
use Tester\Assert;
use Tester\TestCase;

/**
 * @testCase
 */
final class ClaimsTest extends TestCase
{
    /**
     * @return iterable<string, array{
     *     claims: Claims,
     *     expected: array<string, mixed>,
     * }>
     */
    public function provideClaims(): iterable
    {
        yield 'all claims, named ones over additional' => [
            'claims' => new Claims(
                iss: 'https://app.example.com',
                aud: 'https://hub.example.com/.well-known/mercure',
                sub: 'user-42',
                clientId: 'nette-app',
                additional: [
                    'tenant' => 'acme',
                    'iss' => 'overridden',
                ],
            ),
            'expected' => [
                'iss' => 'https://app.example.com',
                'aud' => 'https://hub.example.com/.well-known/mercure',
                'sub' => 'user-42',
                'client_id' => 'nette-app',
                'tenant' => 'acme',
            ],
        ];

        yield 'no claim' => [
            'claims' => new Claims(),
            'expected' => [],
        ];

        yield 'unset claims are omitted' => [
            'claims' => new Claims(
                iss: 'https://app.example.com',
                additional: [
                    'tenant' => 'acme',
                ],
            ),
            'expected' => [
                'iss' => 'https://app.example.com',
                'tenant' => 'acme',
            ],
        ];
    }

    /**
     * @dataProvider provideClaims
     *
     * @param array<string, mixed> $expected
     */
    public function testToArray(Claims $claims, array $expected): void
    {
        Assert::same($expected, $claims->toArray());
    }

    public function testWithDefaultsOnlyFillsMissingClaims(): void
    {
        $hubUrl = 'https://hub.example.com/.well-known/mercure';

        $claims = (new Claims(iss: 'https://app.example.com', additional: [
            'tenant' => 'acme',
        ]))->withPublicUrl($hubUrl);

        $defaults = Claims::fromPublicUrl($hubUrl);
        Assert::equal(new Claims(
            iss: 'https://app.example.com',
            aud: $defaults->aud,
            sub: $defaults->sub,
            clientId: $defaults->clientId,
            additional: [
                'tenant' => 'acme',
            ],
        ), $claims);
        Assert::equal(Claims::fromPublicUrl($hubUrl), (new Claims())->withPublicUrl($hubUrl));

        // a pre-set aud must not be overwritten by the default
        Assert::same(
            'https://custom-aud.example.com',
            (new Claims(aud: 'https://custom-aud.example.com'))->withPublicUrl($hubUrl)->aud,
        );
    }
}

(new ClaimsTest())->run();
