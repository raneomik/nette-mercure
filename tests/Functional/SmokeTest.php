<?php

declare(strict_types=1);

namespace Tests\Functional\Raneomik\NetteMercure;

require \dirname(__DIR__).'/bootstrap.php';

use Tester\Assert;
use Tester\Helpers;
use Tester\HttpAssert;
use Tester\TestCase;
use Tests\Fixtures\Dummies\App\Bootstrap;

/**
 * @testCase
 */
final class SmokeTest extends TestCase
{
    private const string DOMAIN = 'localhost:9876';

    /**
     * @var resource
     */
    private mixed $serverProcess;

    protected function setUp(): void
    {
        $process = proc_open(
            \sprintf('php -S %s -t www', self::DOMAIN),
            [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']],
            $pipes,
            \dirname(__DIR__).'/fixtures/Dummies/App',
        );

        if (! \is_resource($process)) {
            throw new \RuntimeException('Failed to start the server process.');
        }

        usleep(750_000); // Wait for the server to start

        $this->serverProcess ??= $process;

        mkdir(Bootstrap::varDir().'/tmp', recursive: true);
        mkdir(Bootstrap::varDir().'/log', recursive: true);
    }

    protected function tearDown(): void
    {
        Helpers::purge(Bootstrap::varDir());
        rmdir(Bootstrap::varDir());

        proc_terminate($this->serverProcess);
    }

    public function testSubscribePublish(): void
    {
        $response = HttpAssert::fetch(
            \sprintf('http://%s?presenter=Subscribe', self::DOMAIN),
            headers: [
                'X-Requested-With' => 'XMLHttpRequest',
            ],
        );

        $response
            ->expectBody(static function (string $body): bool {
                Assert::contains('"jwtToken":', $body);
                Assert::contains('"hubUrl":', $body);
                Assert::contains('"jwtExtraClaims":', $body);
                Assert::contains('"expiresAt":', $body);

                return true;
            })
            ->expectCode(200)
            ->expectHeader('Content-Type', 'application/json; charset=utf-8')
            ->expectHeader('Access-Control-Expose-Headers', 'Link')
            ->expectHeader('Link', '</.well-known/mercure>; rel="mercure"')
        ;

        $response = HttpAssert::fetch(\sprintf('http://%s?presenter=Publish', self::DOMAIN));
        $response
            ->expectCode(200)
            ->expectBody('{"data":"published"}')
            ->expectHeader('Content-Type', 'application/json; charset=utf-8')
        ;
    }
}

(new SmokeTest())->run();
