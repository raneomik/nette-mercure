<?php

declare(strict_types=1);

namespace Tests\Unit\Raneomik\NetteMercure\Core\Response;

require \dirname(__DIR__, 3).'/bootstrap.php';

use Raneomik\NetteMercure\Bridge\Utils\ConfiguredData;
use Raneomik\NetteMercure\Bridge\Utils\ConfiguredDataRegistry;
use Raneomik\NetteMercure\Core\Subscribe\Authorization;
use Tester\Assert;
use Tester\TestCase;
use Tests\Fixtures\Dummies\Core\DummyRequest;
use Tests\Fixtures\Dummies\Core\DummyResponse;
use Tests\Fixtures\Dummies\Core\Subscribe\DummyJwtProvider;

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
            new ConfiguredDataRegistry([
                'test' => new ConfiguredData(
                    hubName: 'test',
                    hubUrl: 'http://hub.example.com',
                    subscribe: ['*'],
                    publish: ['*'],
                    useCookie: true,
                ),
            ])
        );

        $authorization->createCookie(subscribedTopics: ['test'], hub: 'null');

        Assert::same(
            (new \DateTime('+1 hour'))->format('Y-m-d H:i:s'),
            $response->cookie[Authorization::COOKIE_NAME]['expire']->format('Y-m-d H:i:s'),
        );
        Assert::same(
            'dummy-jwt-token-test-provider-token-test-',
            $response->cookie[Authorization::COOKIE_NAME]['value'],
        );
    }

    /**
     * @testCase
     */
    public function testAuthorizationCookieFromRequest(): void
    {
        $authorization = new Authorization(
            new DummyJwtProvider(),
            new DummyRequest(fromUrl: 'http://example.com/?topics=test&hub=test2'),
            $response = new DummyResponse(),
            new ConfiguredDataRegistry([
                'test' => new ConfiguredData(
                    hubName: 'test',
                    hubUrl: 'http://hub.example.com',
                    subscribe: ['*'],
                    publish: ['*'],
                ),
                'test2' => new ConfiguredData(
                    hubName: 'test2',
                    hubUrl: 'http://hub2.example.com',
                    subscribe: ['*'],
                    publish: ['*'],
                    useCookie: true,
                ),
            ]),
        );

        $authorization->createCookieFromCurrentRequest();

        Assert::same(
            'example.com',
            $response->cookie[Authorization::COOKIE_NAME]['domain'],
        );
        Assert::same(
            'dummy-jwt-token-test-provider-token-test-',
            $response->cookie[Authorization::COOKIE_NAME]['value'],
        );
    }

    /**
     * @testCase
     */
    public function testNoAuthorizationCookieFromRequest(): void
    {
        $authorization = new Authorization(
            new DummyJwtProvider(),
            new DummyRequest(fromUrl: '/?topics=test&hub=test2'),
            $response = new DummyResponse(),
            new ConfiguredDataRegistry(
                [
                    'test' => new ConfiguredData(
                        hubName: 'test',
                        hubUrl: '/',
                        subscribe: ['*'],
                        publish: ['*'],
                        useCookie: false,
                    ),
                ],
            )
        );

        $authorization->createCookieFromCurrentRequest();

        Assert::hasNotKey(Authorization::COOKIE_NAME, $response->cookie);
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
            new ConfiguredDataRegistry([
                'test' => new ConfiguredData(
                    hubName: 'test',
                    hubUrl: 'http://hub.example.com',
                    subscribe: ['*'],
                    publish: ['*'],
                ),
            ])
        );

        $authorization->createCookie(subscribedTopics: ['test']);
        Assert::same(
            'dummy-jwt-token-test-provider-token-test-',
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
            new DummyRequest(fromUrl: 'https://hub.example.com'),
            $response = new DummyResponse(),
            new ConfiguredDataRegistry([
                'test' => new ConfiguredData(
                    hubName: 'test',
                    hubUrl: 'http://hub.example.com',
                    subscribe: ['*'],
                    publish: ['*'],
                ),
            ])
        );

        $authorization->createCookie(subscribedTopics: ['test']);
        Assert::same(
            'dummy-jwt-token-test-provider-token-test-',
            $response->cookie[Authorization::COOKIE_NAME]['value'],
        );

        $authorization->createCookie(subscribedTopics: ['test']);
        Assert::same(
            'dummy-jwt-token-test-provider-token-test-',
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
            new DummyRequest(fromUrl: 'https://example.cz'),
            new DummyResponse(),
            new ConfiguredDataRegistry([
                'test' => new ConfiguredData(
                    hubName: 'test',
                    hubUrl: 'http://hub.example.com',
                    subscribe: ['*'],
                    publish: ['*'],
                ),
            ])
        );

        Assert::exception(
            static fn () => $authorization->createCookie(),
            \RuntimeException::class,
            '~Unable to create authorization cookie for a hub on the different second-level domain~',
        );
    }
}

(new AuthorizationTest())->run();
