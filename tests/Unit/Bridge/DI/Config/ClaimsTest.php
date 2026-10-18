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
    public function testToArray(): void
    {
        $claims = new Claims(
            iss: 'https://app.example.com',
            aud: 'https://hub.example.com/.well-known/mercure',
            sub: 'user-42',
            clientId: 'nette-app',
            additional: [
                'tenant' => 'acme',
                'iss' => 'overridden',
            ],
        );

        Assert::same([
            'iss' => 'https://app.example.com',
            'aud' => 'https://hub.example.com/.well-known/mercure',
            'sub' => 'user-42',
            'client_id' => 'nette-app',
            'tenant' => 'acme',
        ], $claims->toArray());
    }

    public function testUnsetClaimsAreOmitted(): void
    {
        Assert::same([], (new Claims())->toArray());
        Assert::same([
            'iss' => 'https://app.example.com',
            'tenant' => 'acme',
        ], (new Claims(iss: 'https://app.example.com', additional: [
            'tenant' => 'acme',
        ]))->toArray());
    }

    public function testWithDefaultsOnlyFillsMissingClaims(): void
    {
        $claims = (new Claims(iss: 'https://app.example.com', additional: [
            'tenant' => 'acme',
        ]))->withDefaults();

        Assert::equal(new Claims(
            iss: 'https://app.example.com',
            aud: Claims::defaults()->aud,
            sub: Claims::defaults()->sub,
            clientId: Claims::defaults()->clientId,
            additional: [
                'tenant' => 'acme',
            ],
        ), $claims);
        Assert::equal(Claims::defaults(), (new Claims())->withDefaults());
    }
}

(new ClaimsTest())->run();
