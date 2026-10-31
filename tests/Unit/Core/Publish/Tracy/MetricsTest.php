<?php

declare(strict_types=1);

namespace Tests\Unit\Raneomik\NetteMercure\Core\Publish\Tracy;

require \dirname(__DIR__, 4) . '/bootstrap.php';

use Raneomik\NetteMercure\Core\Publish\Tracy\Metrics;
use Tester\Assert;
use Tester\TestCase;

/**
 * @testCase
 */
final class MetricsTest extends TestCase
{
    public function testMinimalistOptions(): void
    {
        $metrics = new Metrics();

        $metrics->start('test');

        Assert::type('float', $currentMemory = $metrics->getMemory('test'));
        Assert::type('float', $currentDuration = $metrics->getDuration('test'));

        $metrics->stop('test');

        Assert::notSame($currentMemory, $metrics->getMemory('test'));
        Assert::notSame($currentDuration, $metrics->getDuration('test'));
    }

    public function testMemoryIsWhatWasAllocatedBetweenStartAndStop(): void
    {
        $metrics = new Metrics();

        $metrics->start('test');

        $allocated = str_repeat('x', 1_000_000);
        $metrics->stop('test');

        Assert::same(1_000_000, \strlen($allocated));
        Assert::true(1_000_000 <= $metrics->getMemory('test') && 1_500_000 > $metrics->getMemory('test'));
    }

    public function testUnknownKey(): void
    {
        Assert::same(0.0, (new Metrics())->getMemory('unknown'));
        Assert::same(0.0, (new Metrics())->getDuration('unknown'));
    }
}

(new MetricsTest())->run();
