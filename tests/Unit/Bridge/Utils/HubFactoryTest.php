<?php

declare(strict_types=1);

namespace Tests\Unit\Raneomik\NetteMercure\Bridge\Utils;

require \dirname(__DIR__, 3) . '/bootstrap.php';

use Raneomik\NetteMercure\Bridge\Utils\HubFactory;
use Symfony\Component\Mercure\FrankenPhpHub;
use Symfony\Component\Mercure\Hub;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Jwt\StaticTokenProvider;
use Symfony\Component\Mercure\ProtocolVersion;
use Symfony\Component\Mercure\Update;
use Tester\Assert;
use Tester\TestCase;
use Tests\Fixtures\Dummies\Core\DummyJwtFactory;

/**
 * Each method runs in its own process (@testCase), so the mercure_publish() stub does not leak.
 *
 * @testCase
 */
final class HubFactoryTest extends TestCase
{
    public function testHttpHubOutsideFrankenPhp(): void
    {
        Assert::false(\function_exists('mercure_publish'));

        $hub = $this->createHub();

        Assert::type(Hub::class, $hub);
        if ($hub instanceof Hub) {
            Assert::same('http://mercure/.well-known/mercure', $hub->getUrl());
            Assert::same('https://example.com/.well-known/mercure', $hub->getPublicUrl());
            Assert::same(ProtocolVersion::V1, $hub->getProtocolVersion());
            Assert::same('static-jwt', $hub->getProvider()->getJwt());
            Assert::type(DummyJwtFactory::class, $hub->getFactory());
        }
    }

    public function testFrankenPhpHubWhenMercurePublishIsAvailable(): void
    {
        // simulate the FrankenPHP runtime, which provides mercure_publish()
        require_once \dirname(__DIR__, 3) . '/fixtures/FrankenPhp/mercure_publish.php';

        $hub = $this->createHub();

        Assert::type(FrankenPhpHub::class, $hub);
        Assert::same('https://example.com/.well-known/mercure', $hub->getPublicUrl());
        Assert::same(ProtocolVersion::V1, $hub->getProtocolVersion());
        Assert::type(DummyJwtFactory::class, $hub->getFactory());
        Assert::same('urn:uuid:frankenphp-stub', $hub->publish(new Update('test-topic', 'data')));
    }

    private function createHub(): HubInterface
    {
        return HubFactory::create(
            'http://mercure/.well-known/mercure',
            new StaticTokenProvider('static-jwt'),
            new DummyJwtFactory('secret'),
            'https://example.com/.well-known/mercure',
            ProtocolVersion::V1,
        );
    }
}

(new HubFactoryTest())->run();
