<?php

declare(strict_types=1);

namespace Tests\Unit\Raneomik\NetteMercure\Tracy\Value;

require \dirname(__DIR__, 5).'/bootstrap.php';

use Raneomik\NetteMercure\Core\Publish\Latte\TurboStream\Action;
use Raneomik\NetteMercure\Core\Publish\Tracy\Value\BroadcastData;
use Tester\Assert;
use Tester\TestCase;

final class BroadcastDataTest extends TestCase
{
    /**
     * @testCase
     */
    public function testMinimalistOptions(): void
    {
        $bdata = new BroadcastData(
            topics: (array) 'topic',
            data: 'test',
        );

        Assert::same(['topic'], $bdata->getTopics());
        Assert::same('test', $bdata->getData());
        Assert::null($bdata->getTemplate());
        Assert::null($bdata->getAction());
        Assert::count(0, $bdata->getOptions());
    }

    public function testOptionsCleanup(): void
    {
        $bdata = new BroadcastData(
            topics: (array) 'topic',
            data: '{"key":"value"}',
            options: [
                'action' => Action::Update,
                'template' => 'template.latte',
                'meta' => 'metadata',
                'id' => 'id',
            ],
        );

        Assert::same(['topic'], $bdata->getTopics());
        Assert::same('{"key":"value"}', $bdata->getData());
        Assert::same(Action::Update, $bdata->getAction());
        Assert::same('template.latte', $bdata->getTemplate());
        Assert::same([
            'meta' => 'metadata',
            'id' => 'id',
        ], $bdata->getOptions());
    }
}

(new BroadcastDataTest())->run();
