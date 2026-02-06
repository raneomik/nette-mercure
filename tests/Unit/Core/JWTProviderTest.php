<?php

declare(strict_types=1);

namespace Tests\Unit\Raneomik\NetteMercure\Core;

require __DIR__ . '/../../bootstrap.php';

use Nette\Utils\Json;
use Raneomik\NetteMercure\Core\JWTProvider;
use Symfony\Component\Mercure\HubRegistry;
use Symfony\Component\Mercure\Jwt\StaticTokenProvider;
use Symfony\Component\Mercure\MockHub;
use Symfony\Component\Mercure\Update;
use Tester\Assert;
use Tester\TestCase;
use Tests\Fixtures\Dummies\DummyJwtFactory;

class JWTProviderTest extends TestCase
{
	/**
	 * @testCase
	 */
	public function testMissingJWTFactoryException(): void
    {
        $publishCallback = fn(Update $update): string => Json::encode([
            'data' => $update->getData(),
            'topics' => $update->getTopics(),
        ]);
        $jwtProvider = new JWTProvider(
            new HubRegistry(
                $defaultHub = new MockHub(
                    'http://hub.example.com',
                    new StaticTokenProvider('?token?'),
                    $publishCallback,
                ),
                [
                    'h1' => $defaultHub,
                    'h2' => new MockHub(
                        'http://hub2.example.com',
                        new StaticTokenProvider('?token2?'),
                        $publishCallback,
                    ),
                ]
            ),
        );

        Assert::exception(
            fn(): string => $jwtProvider->provide(
                subscribe: 'test',
                publish: 'test',
            ),
            \LogicException::class,
            'The default hub does not contain a token factory.',
        );

        Assert::exception(
            fn(): string => $jwtProvider->provide(
                hubName: 'h2',
                subscribe: 'test',
                publish: 'test',
            ),
            \LogicException::class,
            'The "h2" hub does not contain a token factory.',
        );
    }

	/**
	 * @testCase
	 */
	public function testMinimalisticProvision(): void
	{
        $publishCallback = fn(Update $update): string => Json::encode([
            'data' => $update->getData(),
            'topics' => $update->getTopics(),
        ]);
        $jwtProvider = new JWTProvider(
            new HubRegistry(
                new MockHub(
                    'http://hub.example.com',
                    new StaticTokenProvider('?token?'),
                    $publishCallback,
                    new DummyJwtFactory('secret?'),
                ),
            ),
        );

		Assert::same(
		    'dummy-jwt-token-secret?-test-test',
		    $jwtProvider->provide(
		        subscribe: 'test',
		        publish: 'test',
		    ),
		);
		Assert::same(
		    (new \DateTime('+1hour'))->format('Y-m-d H:i:s'),
		    $jwtProvider->ttl()->format('Y-m-d H:i:s'),
		);
		Assert::same(
		    'http://hub.example.com',
		    $jwtProvider->hubUrl(),
		);
	}

	/**
	 * @testCase
	 */
	public function testCustomCookieLifetime(): void
	{
        $publishCallback = fn(Update $update): string => Json::encode([
            'data' => $update->getData(),
            'topics' => $update->getTopics(),
        ]);
        $jwtProvider = new JWTProvider(
            new HubRegistry(
                new MockHub(
                    'http://hub.example.com',
                    new StaticTokenProvider('?token?'),
                    $publishCallback,
                    new DummyJwtFactory('secret?'),
                ),
            ),
        );

		Assert::same(
		    'dummy-jwt-token-secret?-test-test',
		    $jwtProvider->provide(
		        subscribe: 'test',
		        publish: 'test',
		        additionalClaims: [
		            'exp' => 1800,
		        ],
		    ),
		);
		Assert::same(
		    (new \DateTime('+ 30 minutes'))->format('Y-m-d H:i:s'),
		    $jwtProvider->ttl()->format('Y-m-d H:i:s'),
		);
	}

	/**
	 * @testCase
	 */
	public function testMultipleProvisions(): void
	{
        $publishCallback = fn(Update $update): string => Json::encode([
            'data' => $update->getData(),
            'topics' => $update->getTopics(),
        ]);
        $jwtProvider = new JWTProvider(
            new HubRegistry(
                $defaultHub = new MockHub(
                    'http://hub.example.com',
                    new StaticTokenProvider('?token?'),
                    $publishCallback,
                    new DummyJwtFactory('secret?'),
                ),
                [
                    'h1' => $defaultHub,
                    'h2' => new MockHub(
                        'http://hub2.example.com',
                        new StaticTokenProvider('?token2?'),
                        $publishCallback,
                        new DummyJwtFactory('secret2?'),
                    ),
                ]
            ),
        );

		Assert::same(
		    'dummy-jwt-token-secret?-test-test',
		    $jwtProvider->provide(
		        subscribe: 'test',
		        publish: 'test',
		    ),
		);
		Assert::same(
		    (new \DateTime('+1hour'))->format('Y-m-d H:i:s'),
		    $jwtProvider->ttl()->format('Y-m-d H:i:s'),
		);

		Assert::same(
		    'http://hub.example.com',
		    $jwtProvider->hubUrl(),
		);
        Assert::same(
            'dummy-jwt-token-secret2?-test-test',
            $jwtProvider->provide(
                hubName: 'h2',
                subscribe: 'test',
                publish: 'test',
            ),
        );
        Assert::same(
            (new \DateTime('+1hour'))->format('Y-m-d H:i:s'),
            $jwtProvider->ttl()->format('Y-m-d H:i:s'),
        );
        Assert::same(
            'http://hub2.example.com',
            $jwtProvider->hubUrl('h2'),
        );

    }
}

(new JWTProviderTest())->run();
