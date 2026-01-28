<?php

declare(strict_types=1);

namespace Tests\Unit\Tracy\Model;

require dirname(__DIR__, 4) . '/bootstrap.php';

use Nette\Mercure\Bridge\Tracy\Model\HubData;
use Nette\Mercure\Core\Broadcasters;
use Nette\Mercure\Core\PlainBroadcaster;
use Symfony\Component\Mercure\Jwt\StaticTokenProvider;
use Symfony\Component\Mercure\MockHub;
use Symfony\Component\Mercure\Update;
use Tester\Assert;
use Tester\TestCase;

class HubDataTest extends TestCase
{
	public function testMinmalistOptions(): void
	{
		$publishcallback = fn(Update $update): string => $update->getData();
		$broadcasters = new Broadcasters([
			'test' => new PlainBroadcaster(
				new MockHub(
					'test',
					new StaticTokenProvider('!ChangeMe1!'),
					$publishcallback,
				),
			)
		]);

		$hubData = new HubData($broadcasters);

		Assert::type(HubData::class, $hubData);
	}
}


(new HubDataTest)->run();
