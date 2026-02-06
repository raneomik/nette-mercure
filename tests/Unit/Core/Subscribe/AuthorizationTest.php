<?php

declare(strict_types=1);

namespace Tests\Unit\Raneomik\NetteMercure\Core\Response;

require \dirname(__DIR__, 3).'/bootstrap.php';

use Raneomik\NetteMercure\Core\Subscribe\Authorization;
use Tester\Assert;
use Tester\TestCase;
use Tests\Fixtures\Dummies\DummyJwtProvider;
use Tests\Fixtures\Dummies\DummyRequest;
use Tests\Fixtures\Dummies\DummyResponse;

final class AuthorizationTest extends TestCase
{
    /**
     * @testCase
     */
    public function testMinimalisticAuthorizationCookie(): void
    {
        $authorization = new Authorization(
            new DummyJwtProvider(),
            new DummyRequest(),
            $response = new DummyResponse(),
        );

        $authorization->createCookie(subscribe: ['test'], publish: ['test'], hub: 'null');

        Assert::same(
            (new \DateTime('+1 hour'))->format('Y-m-d H:i:s'),
            $response->cookie[Authorization::COOKIE_NAME]['expire']->format('Y-m-d H:i:s'),
        );
        Assert::same(
            'dummy-jwt-token-test-provider-token-test-test',
            $response->cookie[Authorization::COOKIE_NAME]['value'],
        );
    }

    /**
     * @testCase
     */
    public function testCookieFromSubdomain(): void
    {
        $authorization = new Authorization(
            new DummyJwtProvider(),
            new DummyRequest(fromUrl: 'https://hub.mercure.example.com'),
            $response = new DummyResponse(),
        );

        $authorization->createCookie(subscribe: ['test'], publish: ['test']);
        Assert::same(
            'dummy-jwt-token-test-provider-token-test-test',
            $response->cookie[Authorization::COOKIE_NAME]['value'],
        );
    }

    /**
     * @testCase
     */
    public function testCookieFromSameMainDomain(): void
    {
        $authorization = new Authorization(
            new DummyJwtProvider(),
            $request = new DummyRequest(fromUrl: 'https://example.com'),
            $response = new DummyResponse(),
        );

        $authorization->createCookie(subscribe: ['test'], publish: ['test']);
        Assert::same(
            'dummy-jwt-token-test-provider-token-test-test',
            $response->cookie[Authorization::COOKIE_NAME]['value'],
        );

        $request->fromUrl = 'https://hub.example.com';
        $authorization->createCookie(subscribe: ['test'], publish: ['test']);
        Assert::same(
            'dummy-jwt-token-test-provider-token-test-test',
            $response->cookie[Authorization::COOKIE_NAME]['value'],
        );
    }

    /**
     * @testCase
     */
    public function testCreationExceptionCookie(): void
    {
        $authorization = new Authorization(
            new DummyJwtProvider(),
            $request = new DummyRequest(fromUrl: 'https://example.cz'),
            new DummyResponse(),
        );

        Assert::exception(
            static fn () => $authorization->createCookie(),
            \RuntimeException::class,
            '~Unable to create authorization cookie for a hub on the different second-level domain~',
        );
    }
}

(new AuthorizationTest())->run();
