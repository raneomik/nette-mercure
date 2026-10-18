<?php

declare(strict_types=1);

namespace Tests\Unit\Raneomik\NetteMercure\Core\Subscribe;

require \dirname(__DIR__, 3) . '/bootstrap.php';

use Raneomik\NetteMercure\Bridge\DI\Config\Configuration;
use Raneomik\NetteMercure\Bridge\DI\Config\ConfigurationRegistry;
use Raneomik\NetteMercure\Core\Subscribe\Authorization;
use Symfony\Component\Mercure\ProtocolVersion;
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
            new ConfigurationRegistry([
                'test' => new Configuration(
                    hubName: 'test',
                    hubUrl: 'http://hub.example.com',
                    publicUrl: 'http://hub.example.com',
                    subscribe: ['*'],
                    publish: ['*'],
                    protocolVersion: ProtocolVersion::Legacy,
                    useCookie: true,
                ),
            ])
        );

        $authorization->createCookie(subscribedTopics: ['test'], hub: 'null');

        Assert::same(
            (new \DateTime('+1 hour'))->format('Y-m-d H:i:s'),
            $response->cookie[ProtocolVersion::Legacy->getDefaultCookieName()]['expire']->format('Y-m-d H:i:s'),
        );
        Assert::same(
            'dummy-jwt-token-test-provider-token-test-',
            $response->cookie[ProtocolVersion::Legacy->getDefaultCookieName()]['value'],
        );
    }

    public function testAuthorizationCookieFromRequest(): void
    {
        $authorization = new Authorization(
            new DummyJwtProvider(),
            new DummyRequest(fromUrl: 'http://example.com/?topics=test&hub=test2'),
            $response = new DummyResponse(),
            new ConfigurationRegistry([
                'test' => new Configuration(
                    hubName: 'test',
                    hubUrl: 'http://hub.example.com',
                    publicUrl: 'http://hub.example.com',
                    subscribe: ['*'],
                    publish: ['*'],
                    protocolVersion: ProtocolVersion::Legacy,
                ),
                'test2' => new Configuration(
                    hubName: 'test2',
                    hubUrl: 'http://hub2.example.com',
                    publicUrl: 'http://hub2.example.com',
                    subscribe: ['*'],
                    publish: ['*'],
                    protocolVersion: ProtocolVersion::Legacy,
                    useCookie: true,
                ),
            ]),
        );

        $authorization->createCookieFromCurrentRequest();

        Assert::same(
            '/',
            $response->cookie[ProtocolVersion::Legacy->getDefaultCookieName()]['path'],
        );
        Assert::false(
            $response->cookie[ProtocolVersion::Legacy->getDefaultCookieName()]['secure'],
        );
        Assert::same(
            'example.com',
            $response->cookie[ProtocolVersion::Legacy->getDefaultCookieName()]['domain'],
        );
        Assert::same(
            'dummy-jwt-token-test-provider-token-test-',
            $response->cookie[ProtocolVersion::Legacy->getDefaultCookieName()]['value'],
        );
    }

    public function testNoAuthorizationCookieFromRequestOnRelativeHub(): void
    {
        $authorization = new Authorization(
            new DummyJwtProvider(),
            new DummyRequest(fromUrl: '/?topics=test&hub=test2'),
            $response = new DummyResponse(),
            new ConfigurationRegistry(
                [
                    'test' => new Configuration(
                        hubName: 'test',
                        hubUrl: '/',
                        publicUrl: '/',
                        subscribe: ['*'],
                        publish: ['*'],
                        protocolVersion: ProtocolVersion::Legacy,
                        useCookie: false,
                    ),
                ],
            )
        );

        $authorization->createCookieFromCurrentRequest();

        Assert::hasNotKey(ProtocolVersion::Legacy->getDefaultCookieName(), $response->cookie);
    }

    public function testAlternativeValuesOnRelativeHub(): void
    {
        $authorization = new Authorization(
            $provider = new DummyJwtProvider(),
            new DummyRequest(fromUrl: '/publish?hubName=test&claims[exp]=300'),
            $response = new DummyResponse(),
            new ConfigurationRegistry(
                [
                    'test' => new Configuration(
                        hubName: 'test',
                        hubUrl: '/publish',
                        publicUrl: '/publish',
                        subscribe: ['*'],
                        publish: ['*'],
                        protocolVersion: ProtocolVersion::Legacy,
                        useCookie: true,
                    ),
                ],
            )
        );

        $authorization->createCookieFromCurrentRequest();

        Assert::null(
            $response->cookie[ProtocolVersion::Legacy->getDefaultCookieName()]['domain'],
            'null domain cookie set on same domain/relative path'
        );
        Assert::same(
            '/publish',
            $response->cookie[ProtocolVersion::Legacy->getDefaultCookieName()]['path'],
        );
        Assert::true(
            $response->cookie[ProtocolVersion::Legacy->getDefaultCookieName()]['secure'],
        );
        Assert::true(
            $response->cookie[ProtocolVersion::Legacy->getDefaultCookieName()]['httpOnly'],
        );
        Assert::same(
            'dummy-jwt-token-test-provider-token-*-',
            $response->cookie[ProtocolVersion::Legacy->getDefaultCookieName()]['value'],
        );
        Assert::same(
            $provider->ttl(),
            $response->cookie[ProtocolVersion::Legacy->getDefaultCookieName()]['expire'],
        );
    }

    public function testAuthorizationCookieFromRequestOnRelativeHub(): void
    {
        $authorization = new Authorization(
            new DummyJwtProvider(),
            new DummyRequest(fromUrl: '/?topics=test&hub=test2'),
            $response = new DummyResponse(),
            new ConfigurationRegistry(
                [
                    'test' => new Configuration(
                        hubName: 'test',
                        hubUrl: '/',
                        publicUrl: '/',
                        subscribe: ['*'],
                        publish: ['*'],
                        protocolVersion: ProtocolVersion::Legacy,
                        useCookie: true,
                    ),
                ],
            )
        );

        $authorization->createCookieFromCurrentRequest();

        Assert::hasKey(ProtocolVersion::Legacy->getDefaultCookieName(), $response->cookie);
    }

    public function testCookieFromSubdomain(): void
    {
        $authorization = new Authorization(
            new DummyJwtProvider(),
            new DummyRequest(fromUrl: 'https://hub.mercure.example.com'),
            $response = new DummyResponse(),
            new ConfigurationRegistry([
                'test' => new Configuration(
                    hubName: 'test',
                    hubUrl: 'http://hub.example.com',
                    publicUrl: 'http://hub.example.com',
                    subscribe: ['*'],
                    publish: ['*'],
                    protocolVersion: ProtocolVersion::Legacy,
                ),
            ])
        );

        $authorization->createCookie(subscribedTopics: ['test']);
        Assert::same(
            'dummy-jwt-token-test-provider-token-test-',
            $response->cookie[ProtocolVersion::Legacy->getDefaultCookieName()]['value'],
        );
    }

    public function testCookieFromSameMainDomain(): void
    {
        $authorization = new Authorization(
            new DummyJwtProvider(),
            new DummyRequest(fromUrl: 'https://hub.example.com'),
            $response = new DummyResponse(),
            new ConfigurationRegistry([
                'test' => new Configuration(
                    hubName: 'test',
                    hubUrl: 'http://hub.example.com',
                    publicUrl: 'http://hub.example.com',
                    subscribe: ['*'],
                    publish: ['*'],
                    protocolVersion: ProtocolVersion::Legacy,
                ),
            ])
        );

        $authorization->createCookie(subscribedTopics: ['test']);
        Assert::same(
            'dummy-jwt-token-test-provider-token-test-',
            $response->cookie[ProtocolVersion::Legacy->getDefaultCookieName()]['value'],
        );

        $authorization->createCookie(subscribedTopics: ['test']);
        Assert::same(
            'dummy-jwt-token-test-provider-token-test-',
            $response->cookie[ProtocolVersion::Legacy->getDefaultCookieName()]['value'],
        );
    }

    public function testCreationExceptionCookie(): void
    {
        $authorization = new Authorization(
            new DummyJwtProvider(),
            new DummyRequest(fromUrl: 'https://example.cz'),
            new DummyResponse(),
            new ConfigurationRegistry([
                'test' => new Configuration(
                    hubName: 'test',
                    hubUrl: 'http://hub.example.com',
                    publicUrl: 'http://hub.example.com',
                    subscribe: ['*'],
                    publish: ['*'],
                    protocolVersion: ProtocolVersion::Legacy,
                ),
            ])
        );

        Assert::exception(
            static fn () => $authorization->createCookie(),
            \RuntimeException::class,
            '~Unable to create authorization cookie for a hub on the different second-level domain~',
        );
    }

    public function testV1ProtocolCookie(): void
    {
        $authorization = new Authorization(
            new DummyJwtProvider(),
            new DummyRequest(fromUrl: 'https://example.com'),
            $response = new DummyResponse(),
            new ConfigurationRegistry([
                'test' => new Configuration(
                    hubName: 'test',
                    hubUrl: 'http://mercure:3000/.well-known/mercure',
                    publicUrl: 'https://hub.example.com/.well-known/mercure',
                    subscribe: ['*'],
                    publish: ['*'],
                    protocolVersion: ProtocolVersion::V1,
                ),
            ])
        );

        $authorization->createCookie(subscribedTopics: ['test']);

        Assert::hasNotKey(ProtocolVersion::Legacy->getDefaultCookieName(), $response->cookie);

        $cookie = $response->cookie[ProtocolVersion::V1->getDefaultCookieName()];
        Assert::same('dummy-jwt-token-test-provider-token-test-', $cookie['value']);
        Assert::same('/.well-known/mercure', $cookie['path']);
        Assert::same('example.com', $cookie['domain']);
        Assert::true($cookie['secure']);
    }

    public function testV1ProtocolCookieRequiresHttps(): void
    {
        $authorization = new Authorization(
            new DummyJwtProvider(),
            new DummyRequest(fromUrl: 'http://example.com'),
            new DummyResponse(),
            new ConfigurationRegistry([
                'test' => new Configuration(
                    hubName: 'test',
                    hubUrl: 'http://hub.example.com',
                    publicUrl: 'http://hub.example.com',
                    subscribe: ['*'],
                    publish: ['*'],
                    protocolVersion: ProtocolVersion::V1,
                ),
            ])
        );

        Assert::exception(
            static fn () => $authorization->createCookie(),
            \InvalidArgumentException::class,
            '~"__Secure-mercure_access_token" cookie name requires a hub public URL served over HTTPS~',
        );
    }
}

(new AuthorizationTest())->run();
