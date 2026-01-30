<?php

declare(strict_types=1);

namespace Tests\Unit\Raneomik\NetteMercure\Tracy\Model;

require dirname(__DIR__, 4) . '/bootstrap.php';

use Raneomik\NetteMercure\Bridge\Tracy\Model\HubData;
use Raneomik\NetteMercure\Core\Broadcasters;
use Raneomik\NetteMercure\Core\PlainBroadcaster;
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
		    ),
		]);

		$hubData = new HubData($broadcasters);

		Assert::type(HubData::class, $hubData);
	}
}

(new HubDataTest())->run();
