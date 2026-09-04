<?php

declare(strict_types=1);

namespace Tests\Unit\Raneomik\NetteMercure\Core\Publish\Latte;

require \dirname(__DIR__, 4).'/bootstrap.php';

use Latte\Engine;
use Latte\RuntimeException;
use Nette\Utils\Json;
use Raneomik\NetteMercure\Core\Publish\Latte\TemplatePathResolver;
use Raneomik\NetteMercure\Core\Publish\Latte\TemplatingBroadcaster;
use Raneomik\NetteMercure\Core\Publish\Latte\TurboStreamAction;
use Raneomik\NetteMercure\Core\Publish\PlainBroadcaster;
use Raneomik\NetteMercure\Exception\BroadcastException;
use Symfony\Component\Mercure\HubInterface;
use Tester\Assert;
use Tester\TestCase;
use Tests\Fixtures\Dummies\Core\MockHubFactory;

/**
 * @testCase
 */
final class TemplatingBroadcasterTest extends TestCase
{
    private HubInterface $broadcasterHub;

    private TemplatingBroadcaster $broadcaster;

    private TemplatePathResolver $templatePathResolver;

    protected function setUp(): void
    {
        $this->broadcaster = new TemplatingBroadcaster(
            new PlainBroadcaster(
                $this->broadcasterHub = MockHubFactory::create('http://example.com/hub'),
            ),
            $this->templatePathResolver = new TemplatePathResolver(\dirname(__DIR__, 4).'/fixtures/templates'),
            new Engine(),
        );
    }

    public function testException(): void
    {
        Assert::exception(
            fn (): string => $this->broadcaster->broadcast('test', 'Hello Mercure!', [
                'template' => '/fixtures/inexistent.latte',
            ]),
            BroadcastException::class,
            \sprintf('Template file "%1$s/fixtures/inexistent.latte" not found in "%1$s".', $this->templatePathResolver->basePath()),
        );
    }

    public function testMinimalisticBroadcast(): void
    {
        Assert::same(
            Json::encode([
                'data' => 'test',
                'topics' => ['test'],
            ]),
            $this->broadcaster->broadcast(
                'test',
                'test',
            ),
        );

        Assert::same([
            'rendered_data' => 'test',
        ], $this->broadcaster->broadcastOptions());

        Assert::same(
            Json::encode([
                'data' => "Hello Mercure!\n",
                'topics' => ['test'],
            ]),
            $this->broadcaster->broadcast(
                'test',
                [
                    'data' => 'Hello Mercure!',
                ],
                template: 'example.latte',
            ),
        );

        Assert::same($this->broadcasterHub, $this->broadcaster->broadcasterHub());
        Assert::same('http://example.com/hub', $this->broadcaster->broadcasterUrl());
        Assert::same([
            'template' => $this->templatePathResolver->resolvedDir().'/example.latte',
            'rendered_data' => 'Hello Mercure!'.PHP_EOL,
        ], $this->broadcaster->broadcastOptions());
    }

    public function testJsonBroadcast(): void
    {
        Assert::same(
            Json::encode([
                'data' => Json::encode([
                    'id' => '1',
                    'title' => 'Mercure',
                ], pretty: true).PHP_EOL,
                'topics' => ['test'],
            ]),
            $this->broadcaster->broadcast(
                'test',
                [
                    'id' => '1',
                    'title' => 'Mercure',
                ],
                template: 'example.json.latte',
            ),
        );
    }

    public function testStreamBroadcast(): void
    {
        Assert::same(
            Json::encode([
                'data' => "\tHello Mercure!\n",
                'topics' => ['test'],
                'sse_type' => 'turbo-stream',
            ]),
            $this->broadcaster->broadcast(
                'test',
                'Hello Mercure!',
                [
                    'action' => TurboStreamAction::Update,
                ],
                template: 'exampleStream.latte',
            ),
        );

        Assert::exception(
            fn (): string => $this->broadcaster->broadcast(
                'test',
                'Hello Mercure!',
                [
                    'action' => TurboStreamAction::Remove,
                ],
                template: 'exampleStream.latte',
            ),
            RuntimeException::class,
            "Cannot include undefined block 'remove'.",
        );
    }
}

(new TemplatingBroadcasterTest())->run();
