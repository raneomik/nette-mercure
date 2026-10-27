<?php

declare(strict_types=1);

namespace Tests\Unit\Raneomik\NetteMercure\Core\Publish\Tracy\Value;

require \dirname(__DIR__, 5) . '/bootstrap.php';

use Nette\Utils\Json;
use Raneomik\NetteMercure\Core\Publish\Latte\TurboStreamAction;
use Raneomik\NetteMercure\Core\Publish\Tracy\Value\BroadcastData;
use Raneomik\NetteMercure\Core\Publish\Tracy\Value\BroadcastStatus;
use Tester\Assert;
use Tester\TestCase;

/**
 * @testCase
 */
final class BroadcastDataTest extends TestCase
{
    public function testMinimalistOptions(): void
    {
        $bdata = new BroadcastData(
            BroadcastStatus::Sent,
            topics: (array) 'topic',
            data: 'test',
        );

        Assert::same(['topic'], $bdata->topics);
        Assert::same('test', $bdata->data);
        Assert::null($bdata->template());
        Assert::null($bdata->action());
        Assert::count(0, $bdata->options());
    }

    public function testOptionsCleanup(): void
    {
        $bdata = new BroadcastData(
            BroadcastStatus::Sent,
            topics: (array) 'topic',
            data: '{"key":"value"}',
            options: [
                'action' => TurboStreamAction::Update,
                'template' => 'template.latte',
                'meta' => 'metadata',
                'id' => 'id',
            ],
        );

        Assert::same(['topic'], $bdata->topics);
        Assert::same('{"key":"value"}', $bdata->data);
        Assert::same(TurboStreamAction::Update, $bdata->action());
        Assert::same('template.latte', $bdata->template());
        Assert::same([
            'meta' => 'metadata',
            'id' => 'id',
        ], $bdata->options());
    }

    public function testJsonSerializationIsLeanAndSafe(): void
    {
        $bdata = new BroadcastData(
            BroadcastStatus::Error,
            topics: ['topic'],
            data: [
                'big' => str_repeat('a', 5000),
            ],
            sentAt: 1_700_000_000_000.0,
            size: 5010.0,
            options: [
                'action' => TurboStreamAction::Append,
                'template' => null,
                'target' => 'messages',
                'sse_type' => 'turbo-stream',
                'private' => true,
                'rendered_data' => str_repeat('b', 5000),
            ],
            error: new \RuntimeException('boom'),
            id: 'urn:uuid:1',
            hub: 'one',
        );

        $json = Json::decode(Json::encode($bdata), forceArrays: true);

        Assert::same('urn:uuid:1', $json['id']);
        Assert::same('one', $json['hub']);
        Assert::same('error', $json['status']);
        Assert::same('append', $json['action']);
        Assert::null($json['template']);
        Assert::same('messages', $json['target']);
        Assert::same('turbo-stream', $json['type']);
        Assert::true($json['private']);
        Assert::true(400 >= mb_strlen($json['preview']), 'a preview, never a second copy of the message');
        Assert::same([
            'class' => \RuntimeException::class,
            'message' => 'boom',
        ], $json['error']);
        Assert::false(isset($json['data']) || isset($json['renderedData']));
    }
}

(new BroadcastDataTest())->run();
