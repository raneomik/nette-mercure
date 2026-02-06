<?php

declare(strict_types=1);

namespace Tests\Unit\Raneomik\NetteMercure\Bridge\Utils;

require \dirname(__DIR__, 3).'/bootstrap.php';

use Raneomik\NetteMercure\Bridge\Utils\BroadcastersLoader;
use Raneomik\NetteMercure\Core\Publish\Broadcasters;
use Raneomik\NetteMercure\Core\Publish\PlainBroadcaster;
use Symfony\Component\Mercure\Jwt\StaticTokenProvider;
use Symfony\Component\Mercure\MockHub;
use Symfony\Component\Mercure\Update;
use Tester\Assert;
use Tester\TestCase;

final class BroadcastersLoaderTest extends TestCase
{
    /**
     * @testCase
     */
    public function testLoad(): void
    {
        $publishCallback = static fn (Update $update): string => $update->getData();

        $broadcasters = new Broadcasters([
            'test' => new PlainBroadcaster(
                new MockHub(
                    'test',
                    new StaticTokenProvider('!ChangeMe1!'),
                    $publishCallback,
                ),
            ),
        ]);

        $loader = new BroadcastersLoader(static fn (): Broadcasters => $broadcasters);

        Assert::type(Broadcasters::class, $loader());
    }
}

(new BroadcastersLoaderTest())->run();
