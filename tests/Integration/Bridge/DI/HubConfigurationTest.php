<?php

declare(strict_types=1);

namespace Tests\Integration\Raneomik\NetteMercure\Bridge\DI;

require \dirname(__DIR__, 3) . '/bootstrap.php';

use Nette\DI\InvalidConfigurationException;
use Raneomik\NetteMercure\Bridge\DI\Config\ConfigurationRegistry;
use Symfony\Component\Mercure\FrankenPhpHub;
use Symfony\Component\Mercure\Hub;
use Symfony\Component\Mercure\ProtocolVersion;
use Tester\Assert;

/**
 * Hub-level configuration: URLs, protocol version and runtime hub selection.
 * Each test method runs in its own process (@testCase), so the mercure_publish() stub cannot leak between them.
 *
 * @testCase
 */
final class HubConfigurationTest extends ContainerTestCase
{
    public function testPublicUrlConfiguration(): void
    {
        $container = $this->createContainer('
        mercure:
            url: http://mercure:3000/.well-known/mercure
            publicUrl: https://hub.example.com/.well-known/mercure
            jwt:
                key: n3tt3-m3rcur3-t3st-jwt-s3cr3t-k3y-32
        ');

        /** @var Hub $hub */
        $hub = $container->getService('mercure.sf.hub.default');
        Assert::same('http://mercure:3000/.well-known/mercure', $hub->getUrl());
        Assert::same('https://hub.example.com/.well-known/mercure', $hub->getPublicUrl());
        Assert::same(ProtocolVersion::V1, $hub->getProtocolVersion());

        /** @var ConfigurationRegistry $registry */
        $registry = $container->getService('mercure.hubsConfiguration');
        Assert::same('https://hub.example.com/.well-known/mercure', $registry->getConfiguration()->publicUrl);
        Assert::same(ProtocolVersion::V1, $registry->getConfiguration()->protocolVersion);
    }

    public function testUnknownProtocolVersion(): void
    {
        Assert::exception(
            fn (): \Nette\DI\Container => $this->createContainer('
            mercure:
                url: https://hub.example.com/.well-known/mercure
                protocolVersion: "2.0"
                jwt:
                    key: jwt-secret
            '),
            InvalidConfigurationException::class,
            "%a%protocolVersion' expects to be %a%ProtocolVersion, '2.0' given.",
        );
    }

    public function testHubIsPickedAtRuntime(): void
    {
        // compiled outside FrankenPHP (e.g. by a CLI command) …
        $container = $this->createContainer('
        mercure:
            url: /.well-known/mercure
            jwt:
                key: jwt-secret
        ');

        // … then run in FrankenPHP, which provides mercure_publish()
        require_once \dirname(__DIR__, 3) . '/fixtures/FrankenPhp/mercure_publish.php';

        Assert::type(FrankenPhpHub::class, $container->getService('mercure.sf.hub.default'));
    }

    public function testSingleHubConfigurationWithoutUrl(): void
    {
        // "url" has a default value: "jwt" must not be mistaken for a hub name
        $container = $this->createContainer('
        mercure:
            jwt:
                key: jwt-secret
        ');

        Assert::type(Hub::class, $container->getService('mercure.sf.hub.default'));
        Assert::false($container->hasService('mercure.sf.hub.jwt'));
    }
}

(new HubConfigurationTest())->run();
