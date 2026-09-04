<?php

declare(strict_types=1);

namespace Tests\Unit\Raneomik\NetteMercure\Core\Subscribe;

require \dirname(__DIR__, 3).'/bootstrap.php';

use Raneomik\NetteMercure\Bridge\DI\Config\ConfiguredData;
use Raneomik\NetteMercure\Bridge\DI\Config\ConfiguredDataRegistry;
use Raneomik\NetteMercure\Core\Subscribe\Authorization;
use Tester\Assert;
use Tester\TestCase;
use Tests\Fixtures\Dummies\Core\DummyRequest;
use Tests\Fixtures\Dummies\Core\DummyResponse;
use Tests\Fixtures\Dummies\Core\Subscribe\DummyJwtProvider;

/**
 * @testCase
 */
final class AuthorizationTest extends TestCase
{
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
            '/',
            $response->cookie[Authorization::COOKIE_NAME]['path'],
        );
        Assert::false(
            $response->cookie[Authorization::COOKIE_NAME]['secure'],
        );
        Assert::same(
            'example.com',
            $response->cookie[Authorization::COOKIE_NAME]['domain'],
        );
        Assert::same(
            'dummy-jwt-token-test-provider-token-test-',
            $response->cookie[Authorization::COOKIE_NAME]['value'],
        );
    }

    public function testNoAuthorizationCookieFromRequestOnRelativeHub(): void
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

    public function testAlternativeValuesOnRelativeHub(): void
    {
        $authorization = new Authorization(
            $provider = new DummyJwtProvider(),
            new DummyRequest(fromUrl: '/publish?hubName=test&claims[exp]=300'),
            $response = new DummyResponse(),
            new ConfiguredDataRegistry(
                [
                    'test' => new ConfiguredData(
                        hubName: 'test',
                        hubUrl: '/publish',
                        subscribe: ['*'],
                        publish: ['*'],
                        useCookie: true,
                    ),
                ],
            )
        );

        $authorization->createCookieFromCurrentRequest();

        Assert::null(
            $response->cookie[Authorization::COOKIE_NAME]['domain'],
            'null domain cookie set on same domain/relative path'
        );
        Assert::same(
            '/publish',
            $response->cookie[Authorization::COOKIE_NAME]['path'],
        );
        Assert::true(
            $response->cookie[Authorization::COOKIE_NAME]['secure'],
        );
        Assert::true(
            $response->cookie[Authorization::COOKIE_NAME]['httpOnly'],
        );
        Assert::same(
            'dummy-jwt-token-test-provider-token-*-',
            $response->cookie[Authorization::COOKIE_NAME]['value'],
        );
        Assert::same(
            $provider->ttl(),
            $response->cookie[Authorization::COOKIE_NAME]['expire'],
        );
    }

    public function testAuthorizationCookieFromRequestOnRelativeHub(): void
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
                        useCookie: true,
                    ),
                ],
            )
        );

        $authorization->createCookieFromCurrentRequest();

        Assert::hasKey(Authorization::COOKIE_NAME, $response->cookie);
    }

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
