<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

require __DIR__ . '/../../bootstrap.php';

use Nette\Mercure\Core\PlainBroadcaster;
use Nette\Utils\Json;
use Symfony\Component\Mercure\Jwt\StaticTokenProvider;
use Symfony\Component\Mercure\MockHub;
use Symfony\Component\Mercure\Update;
use Tester\Assert;
use Tester\TestCase;

class PlainBroadcasterTest extends TestCase
{
	private PlainBroadcaster $broadcaster;

	protected function setUp(): void
	{
		$publishCallback = fn(Update $update): string => Json::encode([
			'data' => $update->getData(),
			'topics' => $update->getTopics(),
		]);

		$this->broadcaster = new PlainBroadcaster(
			new MockHub(
				'http://example.com/hub',
				new StaticTokenProvider('!ChangeMe!'),
				$publishCallback,
			),
		);
	}

	public function testMinimalisticBroadcast(): void
	{
		Assert::same(
			Json::encode([
				'data' => 'Hello, World!',
				'topics' => ['test'],
			]),
			$this->broadcaster->broadcast(
				'test',
				'Hello, World!',
			),
		);

		Assert::same('http://example.com/hub', $this->broadcaster->broadcasterUrl());
		Assert::same(['rendered_data' => 'Hello, World!'], $this->broadcaster->broadcastOptions());

		Assert::same(
			Json::encode([
				'data' => '{"message":"test"}',
				'topics' => ['test'],
			]),
			$this->broadcaster->broadcast(
				'test',
				['message' => 'test'],
			),
		);
		Assert::same(['rendered_data' => '{"message":"test"}'], $this->broadcaster->broadcastOptions());
	}

	public function testJsonBroadcast(): void
	{
		Assert::same(
			Json::encode([
				'data' => '{"message": "Hello, World!"}',
				'topics' => ['test'],
			]),
			$this->broadcaster->broadcast(
				'test',
				'{"message": "Hello, World!"}',
			),
		);
	}
}

(new PlainBroadcasterTest)->run();
