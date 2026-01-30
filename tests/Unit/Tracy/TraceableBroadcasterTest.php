<?php

declare(strict_types=1);

namespace Tests\Unit\Raneomik\NetteMercure\Tracy;

require __DIR__ . '/../../bootstrap.php';

use Nette\Utils\Json;
use Raneomik\NetteMercure\Core\PlainBroadcaster;
use Raneomik\NetteMercure\Latte\TurboStream\Action;
use Raneomik\NetteMercure\Tracy\Metrics;
use Raneomik\NetteMercure\Tracy\Model\BroadcastData;
use Raneomik\NetteMercure\Tracy\TraceableBroadcaster;
use Symfony\Component\Mercure\Jwt\StaticTokenProvider;
use Symfony\Component\Mercure\MockHub;
use Symfony\Component\Mercure\Update;
use Tester\Assert;
use Tester\TestCase;

class TraceableBroadcasterTest extends TestCase
{
	private TraceableBroadcaster $debugBroadcaster;

	private Metrics $metrics;

	protected function setUp(): void
	{
		$publishCallback = fn(Update $update): string => Json::encode([
		    'data' => $update->getData(),
		    'topics' => $update->getTopics(),
		]);

		$this->debugBroadcaster = new TraceableBroadcaster(
		    new PlainBroadcaster(
		        new MockHub(
		            'http://example.com/hub',
		            new StaticTokenProvider('!ChangeMe!'),
		            $publishCallback,
		        ),
		    ),
		    $this->metrics = new Metrics(),
		);
	}

	public function testBroadcastTracing(): void
	{
		Assert::same(
		    Json::encode([
		        'data' => 'Hello, World!',
		        'topics' => ['test'],
		    ]),
		    $this->debugBroadcaster->broadcast(
		        'test',
		        'Hello, World!',
		    ),
		);

		Assert::same('http://example.com/hub', $this->debugBroadcaster->broadcasterUrl());
		Assert::same([
		    'rendered_data' => 'Hello, World!',
		], $this->debugBroadcaster->broadcastOptions());

		Assert::same(
		    $firstMessageMemory = $this->metrics->getMemory($this->debugBroadcaster::class),
		    $this->debugBroadcaster->getMemory(),
		);
		Assert::same(
		    $firstMessageDuration = $this->metrics->getDuration($this->debugBroadcaster::class),
		    $this->debugBroadcaster->getDuration(),
		);
		Assert::same(1, $this->debugBroadcaster->count());
		Assert::count(1, $this->debugBroadcaster->getMessageData());

		$this->debugBroadcaster->broadcast(
		    'test',
		    'Hello, another World!',
		    [
		        'action' => Action::Update,
		        'metadata' => 'second broadcast',
		    ],
		    template: 'uncosidered_in_plain_broadcaster',
		);

		Assert::same(
		    $firstMessageMemory + $this->metrics->getMemory($this->debugBroadcaster::class),
		    $this->debugBroadcaster->getMemory(),
		);
		Assert::same(
		    $firstMessageDuration + $this->metrics->getDuration($this->debugBroadcaster::class),
		    $this->debugBroadcaster->getDuration(),
		);

		Assert::same(2, $this->debugBroadcaster->count());
		Assert::count(2, $this->debugBroadcaster->getMessageData());

		$formatedData = array_map(
		    fn(BroadcastData $data): array => [
		        'topics' => $data->getTopics(),
		        'data' => $data->getData(),
		        'template' => $data->getTemplate(),
		        'action' => $data->getAction(),
		        'options' => $data->getOptions(),
		    ],
		    $this->debugBroadcaster->getMessageData(),
		);
		Assert::same(
		    [
		        [
		            'topics' => ['test'],
		            'data' => 'Hello, World!',
		            'template' => 'n/a',
		            'action' => 'n/a',
		            'options' => [],
		        ],
		        [
		            'topics' => ['test'],
		            'data' => 'Hello, another World!',
		            'template' => 'uncosidered_in_plain_broadcaster',
		            'action' => Action::Update,
		            'options' => [
		                'metadata' => 'second broadcast',
		            ],
		        ],
		    ],
		    $formatedData,
		);
	}
}

(new TraceableBroadcasterTest())->run();
