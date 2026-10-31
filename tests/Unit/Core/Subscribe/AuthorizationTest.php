<?php

declare(strict_types=1);

namespace Tests\Unit\Raneomik\NetteMercure\Core\Subscribe;

require \dirname(__DIR__, 3) . '/bootstrap.php';

use Nette\Http\IResponse;
use Nette\Http\Response;
use Nette\Http\SameSite;
use Raneomik\NetteMercure\Bridge\DI\Config\Configuration;
use Raneomik\NetteMercure\Bridge\DI\Config\ConfigurationRegistry;
use Raneomik\NetteMercure\Core\Subscribe\Authorization;
use Symfony\Component\Mercure\ProtocolVersion;
use Tester\Assert;
use Tester\Environment;
use Tester\TestCase;
use Tests\Fixtures\Dummies\Core\DummyRequest;
use Tests\Fixtures\Dummies\Core\DummyResponse;
use Tests\Fixtures\Dummies\Core\Subscribe\DummyJwtProvider;

/**
 * @testCase
 */
final class AuthorizationTest extends TestCase
{
    /**
     * @return iterable<string, array{
     *     requestUrl: string,
     *     publicUrl: string,
     *     protocolVersion: ProtocolVersion,
     *     expected: array{path: string, domain: ?string, secure: bool},
     * }>
     */
    public function provideCookieParameters(): iterable
    {
        yield 'hub on a subdomain, http' => [
            'requestUrl' => 'http://example.com/',
            'publicUrl' => 'http://hub2.example.com',
            'protocolVersion' => ProtocolVersion::Legacy,
            'expected' => [
                'path' => '/',
                'domain' => 'example.com',
                'secure' => false,
            ],
        ];

        yield 'relative hub' => [
            'requestUrl' => '/publish',
            'publicUrl' => '/publish',
            'protocolVersion' => ProtocolVersion::Legacy,
            'expected' => [
                'path' => '/publish',
                'domain' => null,
                'secure' => true,
            ],
        ];

        yield 'relative root hub' => [
            'requestUrl' => '/',
            'publicUrl' => '/',
            'protocolVersion' => ProtocolVersion::Legacy,
            'expected' => [
                'path' => '/',
                'domain' => null,
                'secure' => true,
            ],
        ];

        yield 'request from a deeper subdomain' => [
            'requestUrl' => 'https://hub.mercure.example.com',
            'publicUrl' => 'http://hub.example.com',
            'protocolVersion' => ProtocolVersion::Legacy,
            'expected' => [
                'path' => '/',
                'domain' => '.example.com',
                'secure' => false,
            ],
        ];

        yield 'same host' => [
            'requestUrl' => 'https://hub.example.com',
            'publicUrl' => 'http://hub.example.com',
            'protocolVersion' => ProtocolVersion::Legacy,
            'expected' => [
                'path' => '/',
                'domain' => null,
                'secure' => false,
            ],
        ];

        yield 'hub host in another case' => [
            'requestUrl' => 'https://example.com',
            'publicUrl' => 'https://HUB.Example.COM',
            'protocolVersion' => ProtocolVersion::Legacy,
            'expected' => [
                'path' => '/',
                'domain' => 'example.com',
                'secure' => true,
            ],
        ];

        yield 'protocol 1.0' => [
            'requestUrl' => 'https://example.com',
            'publicUrl' => 'https://hub.example.com/.well-known/mercure',
            'protocolVersion' => ProtocolVersion::V1,
            'expected' => [
                'path' => '/.well-known/mercure',
                'domain' => 'example.com',
                'secure' => true,
            ],
        ];
    }

    /**
     * @dataProvider provideCookieParameters
     *
     * @param array{path: string, domain: ?string, secure: bool} $expected
     */
    public function testCookieParameters(
        string $requestUrl,
        string $publicUrl,
        ProtocolVersion $protocolVersion,
        array $expected,
    ): void {
        $this->authorization($requestUrl, $publicUrl, $protocolVersion, $response = new DummyResponse())
            ->setCookie(grants: ['test'], hub: 'test');

        // only the cookie of the requested hub, named after its protocol
        Assert::same([$protocolVersion->getDefaultCookieName()], array_keys($response->cookie));

        $cookie = $response->cookie[$protocolVersion->getDefaultCookieName()];
        Assert::same($expected, array_intersect_key($cookie, $expected));
        Assert::same('dummy-jwt-token-test-provider-token-test-', $cookie['value']);
        Assert::true($cookie['httpOnly']);
    }

    /**
     * @return iterable<string, array{
     *     additionalClaims: array<string, mixed>,
     *     expected: string,
     * }>
     */
    public function provideExpirations(): iterable
    {
        yield 'default lifetime' => [
            'additionalClaims' => [],
            'expected' => '+1 hour',
        ];

        yield '"exp" claim' => [
            'additionalClaims' => [
                'exp' => 300,
            ],
            'expected' => '+300 seconds',
        ];
    }

    /**
     * @dataProvider provideExpirations
     *
     * @param array<string, mixed> $additionalClaims
     */
    public function testCookieExpiresWithTheToken(array $additionalClaims, string $expected): void
    {
        $this->authorization('/', 'http://hub.example.com', response: $response = new DummyResponse())
            ->setCookie(grants: ['test'], additionalClaims: $additionalClaims, hub: 'test');

        $expire = $response->cookie[ProtocolVersion::Legacy->getDefaultCookieName()]['expire'];
        Assert::type(\DateTimeInterface::class, $expire);
        Assert::same((new \DateTime($expected))->format('Y-m-d H:i:s'), $expire->format('Y-m-d H:i:s'));
    }

    /**
     * @return iterable<string, array{
     *     requestUrl: string,
     *     publicUrl: string,
     *     protocolVersion: ProtocolVersion,
     *     exception: class-string<\Throwable>,
     *     message: string,
     * }>
     */
    public function provideInvalidCookies(): iterable
    {
        yield 'hub on another second-level domain' => [
            'requestUrl' => 'https://example.cz',
            'publicUrl' => 'http://hub.example.com',
            'protocolVersion' => ProtocolVersion::Legacy,
            'exception' => \RuntimeException::class,
            'message' => '~Unable to create authorization cookie for a hub on the different second-level domain~',
        ];

        yield 'hub on a look-alike domain, which is not a subdomain' => [
            'requestUrl' => 'https://example.com',
            'publicUrl' => 'https://evilexample.com',
            'protocolVersion' => ProtocolVersion::Legacy,
            'exception' => \RuntimeException::class,
            'message' => '~Unable to create authorization cookie~',
        ];

        yield 'hub only sharing the top-level domain' => [
            'requestUrl' => 'https://example.com',
            'publicUrl' => 'https://hub.other.com',
            'protocolVersion' => ProtocolVersion::Legacy,
            'exception' => \RuntimeException::class,
            'message' => '~Unable to create authorization cookie~',
        ];

        yield 'protocol 1.0 without https' => [
            'requestUrl' => 'http://example.com',
            'publicUrl' => 'http://hub.example.com',
            'protocolVersion' => ProtocolVersion::V1,
            'exception' => \InvalidArgumentException::class,
            'message' => '~"__Secure-mercure_access_token" cookie name requires a hub public URL served over HTTPS~',
        ];
    }

    /**
     * @dataProvider provideInvalidCookies
     *
     * @param class-string<\Throwable> $exception
     */
    public function testInvalidCookie(
        string $requestUrl,
        string $publicUrl,
        ProtocolVersion $protocolVersion,
        string $exception,
        string $message,
    ): void {
        $authorization = $this->authorization($requestUrl, $publicUrl, $protocolVersion);

        Assert::exception(static fn () => $authorization->setCookie(hub: 'test'), $exception, $message);
    }

    public function testClearCookieMatchesTheSetOne(): void
    {
        $authorization = $this->authorization('https://example.com', 'https://hub.example.com/.well-known/mercure', ProtocolVersion::V1, $response = new DummyResponse());
        $name = ProtocolVersion::V1->getDefaultCookieName();

        $authorization->setCookie(grants: ['test'], hub: 'test');
        $set = $response->cookie[$name];
        $authorization->clearCookie('test');

        Assert::hasNotKey($name, $response->cookie);
        Assert::same([
            'path' => $set['path'],
            'domain' => $set['domain'],
            'secure' => $set['secure'],
        ], $response->deletedCookie[$name]);
    }

    public function testCookieSameSiteIsOnlyPassedToNetteResponse(): void
    {
        foreach ([SameSite::Strict, null] as $sameSite) {
            $this->authorization('/', 'http://hub.example.com', response: $response = new DummyResponse(), sameSite: $sameSite)
                ->setCookie(grants: ['test'], hub: 'test');

            Assert::null($response->cookie[ProtocolVersion::Legacy->getDefaultCookieName()]['sameSite']);
        }
    }

    public function testCookieWithNetteResponse(): void
    {
        foreach ([SameSite::Lax, null] as $sameSite) {
            $authorization = $this->authorization('/', 'http://hub.example.com', response: new Response(), sameSite: $sameSite);

            Assert::noError(static fn () => $authorization->setCookie(grants: ['test'], hub: 'test'));
        }
    }

    public function testHeaderSentByNetteResponse(): void
    {
        // headers_list() stays empty in CLI
        if (! \function_exists('xdebug_get_headers')) {
            Environment::skip('Needs Xdebug to read the sent headers.');
        }

        // null: the default of Nette\Http\Response applies
        foreach ([[SameSite::Strict, 'Strict'], [null, 'Lax']] as [$sameSite, $expected]) {
            header_remove();
            $this->authorization('/', 'http://hub.example.com', response: new Response(), sameSite: $sameSite)
                ->setCookie(grants: ['test'], hub: 'test');

            $cookies = array_values(array_filter(
                xdebug_get_headers(),
                static fn (string $header): bool => str_starts_with($header, 'Set-Cookie: ' . ProtocolVersion::Legacy->getDefaultCookieName() . '='),
            ));

            Assert::count(1, $cookies);
            Assert::contains('; HttpOnly', $cookies[0]);
            Assert::contains('SameSite=' . $expected, $cookies[0]);
        }
    }

    /**
     * "test" is the second hub: a cookie for the default one would fail the path checks.
     */
    private function authorization(
        string $requestUrl,
        string $publicUrl,
        ProtocolVersion $protocolVersion = ProtocolVersion::Legacy,
        IResponse $response = new DummyResponse(),
        ?SameSite $sameSite = SameSite::Strict,
    ): Authorization {
        $config = new ConfigurationRegistry([
            'other' => new Configuration('other', 'https://other.example.com/other', 'https://other.example.com/other', ['*'], ['*'], protocolVersion: ProtocolVersion::Legacy),
            'test' => new Configuration('test', $publicUrl, $publicUrl, ['*'], ['*'], protocolVersion: $protocolVersion),
        ]);

        return new Authorization(new DummyJwtProvider(), new DummyRequest(fromUrl: $requestUrl), $response, $config, $sameSite);
    }
}

(new AuthorizationTest())->run();
