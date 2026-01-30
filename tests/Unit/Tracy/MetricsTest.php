<?php

declare(strict_types=1);

namespace Tests\Unit\Raneomik\NetteMercure\Tracy;

require __DIR__ . '/../../bootstrap.php';

use Raneomik\NetteMercure\Tracy\Metrics;
use Tester\Assert;
use Tester\TestCase;

class MetricsTest extends TestCase
{
	public function testMinmalistOptions(): void
	{
		$metrics = new Metrics();

		$metrics->start('test');

		Assert::type('float', $currentMemory = $metrics->getMemory('test'));
		Assert::type('float', $currentDuration = $metrics->getDuration('test'));

		$metrics->stop('test');

		Assert::notSame($currentMemory, $stoppedMemory = $metrics->getMemory('test'));
		Assert::notSame($currentDuration, $stoppedDuration = $metrics->getDuration('test'));

		Assert::same(
		    number_format($stoppedMemory / 1000000, 2, '.', "\u{202f}") . "\u{202f}MB",
		    $metrics->formatMemory('test'),
		);
		Assert::same(
		    number_format($stoppedDuration, 2, '.', "\u{202f}") . "\u{202f}ms",
		    $metrics->formatDuration('test'),
		);
	}
}

(new MetricsTest())->run();
