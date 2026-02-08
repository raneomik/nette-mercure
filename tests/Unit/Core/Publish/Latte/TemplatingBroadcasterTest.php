<?php

declare(strict_types=1);

namespace Tests\Unit\Raneomik\NetteMercure\Latte;

require \dirname(__DIR__, 4).'/bootstrap.php';

use Latte\Engine;
use Latte\RuntimeException;
use Nette\Utils\Json;
use Raneomik\NetteMercure\Core\Publish\Latte\TemplatePathResolver;
use Raneomik\NetteMercure\Core\Publish\Latte\TemplatingBroadcaster;
use Raneomik\NetteMercure\Core\Publish\Latte\TurboStream\Action;
use Raneomik\NetteMercure\Core\Publish\PlainBroadcaster;
use Raneomik\NetteMercure\Exception\BroadcastException;
use Tester\Assert;
use Tester\TestCase;
use Tests\Fixtures\Dummies\Core\MockHubFactory;

final class TemplatingBroadcasterTest extends TestCase
{
    private TemplatingBroadcaster $broadcaster;

    private TemplatePathResolver $templatePathResolver;

    protected function setUp(): void
    {
        $this->broadcaster = new TemplatingBroadcaster(
            new PlainBroadcaster(
                MockHubFactory::create('http://example.com/hub'),
            ),
            $this->templatePathResolver = new TemplatePathResolver(\dirname(__DIR__, 4).'/fixtures/templates'),
            new Engine(),
        );
    }

    /**
     * @testCase
     */
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

    /**
     * @testCase
     */
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

        Assert::same('http://example.com/hub', $this->broadcaster->broadcasterUrl());
        Assert::same([
            'template' => $this->templatePathResolver->resolvedDir().'/example.latte',
            'rendered_data' => 'Hello Mercure!'.PHP_EOL,
        ], $this->broadcaster->broadcastOptions());
    }

    /**
     * @testCase
     */
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

    /**
     * @testCase
     */
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
                    'action' => Action::Update,
                ],
                template: 'exampleStream.latte',
            ),
        );

        Assert::exception(
            fn (): string => $this->broadcaster->broadcast(
                'test',
                'Hello Mercure!',
                [
                    'action' => Action::Remove,
                ],
                template: 'exampleStream.latte',
            ),
            RuntimeException::class,
            "Cannot include undefined block 'remove'.",
        );
    }
}

(new TemplatingBroadcasterTest())->run();
