<?php

declare(strict_types=1);

namespace Tests\Unit\Tracy\Model;

require __DIR__ . '/../../../bootstrap.php';


use Nette\Mercure\Tracy\Model\BroadcastData;
use Tester\Assert;
use Tester\TestCase;

class BroadcastDataTest extends TestCase
{
	public function testMinmalistOptions(): void
	{
		$bdata = new BroadcastData(
			topics: (array) 'topic',
			data: 'test',
		);

		Assert::same(['topic'], $bdata->getTopics());
		Assert::same('test', $bdata->getData());
		Assert::null($bdata->getTemplate());
		Assert::null($bdata->getType());
		Assert::count(0, $bdata->getOptions());
	}

	public function testOptionsCleanup(): void
	{
		$bdata = new BroadcastData(
			topics: (array) 'topic',
			data: '{"key":"value"}',
			options: [
				'type' => 'application/json',
				'template' => 'template.latte',
				'meta' => 'metadata',
				'id' => 'id',
			],
		);

		Assert::same(['topic'], $bdata->getTopics());
		Assert::same('{"key":"value"}', $bdata->getData());
		Assert::same('application/json', $bdata->getType());
		Assert::same('template.latte', $bdata->getTemplate());
		Assert::same([
			'meta' => 'metadata',
			'id' => 'id',
		], $bdata->getOptions());
	}
}

(new BroadcastDataTest)->run();
