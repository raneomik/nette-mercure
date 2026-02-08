<?php

declare(strict_types=1);

namespace Tests\Unit\Raneomik\NetteMercure\Bridge\Utils;

require \dirname(__DIR__, 3).'/bootstrap.php';

use Raneomik\NetteMercure\Bridge\Utils\BroadcastersLoader;
use Raneomik\NetteMercure\Core\Publish\Broadcasters;
use Raneomik\NetteMercure\Core\Publish\PlainBroadcaster;
use Tester\Assert;
use Tester\TestCase;
use Tests\Fixtures\Dummies\Core\MockHubFactory;

final class BroadcastersLoaderTest extends TestCase
{
    /**
     * @testCase
     */
    public function testLoad(): void
    {
        $broadcasters = new Broadcasters([
            'test' => new PlainBroadcaster(
                MockHubFactory::create('http://hub.example.com'),
            ),
        ]);

        $loader = new BroadcastersLoader(static fn (): Broadcasters => $broadcasters);

        Assert::type(Broadcasters::class, $loader());
    }
}

(new BroadcastersLoaderTest())->run();
