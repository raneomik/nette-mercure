<?php

declare(strict_types=1);

namespace Tests\Unit\Raneomik\NetteMercure\Core\Publish\Latte;

require \dirname(__DIR__, 4) . '/bootstrap.php';

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
    private const string TEMPLATES = __DIR__ . '/../../../../fixtures/templates';

    private HubInterface $broadcasterHub;

    private TemplatingBroadcaster $broadcaster;

    private TemplatePathResolver $templatePathResolver;

    protected function setUp(): void
    {
        $this->broadcaster = new TemplatingBroadcaster(
            new PlainBroadcaster(
                $this->broadcasterHub = MockHubFactory::create('http://example.com/hub'),
            ),
            $this->templatePathResolver = new TemplatePathResolver(self::TEMPLATES),
            new Engine(),
        );
    }

    /**
     * @return iterable<string, array{
     *     data: array<string, string>|string,
     *     options: array<string, mixed>,
     *     template: ?string,
     *     expected: array<string, mixed>,
     * }>
     */
    public function provideBroadcasts(): iterable
    {
        yield 'no template' => [
            'data' => 'test',
            'options' => [],
            'template' => null,
            'expected' => [
                'data' => 'test',
                'topics' => ['test'],
            ],
        ];

        yield 'latte template' => [
            'data' => [
                'data' => 'Hello Mercure!',
            ],
            'options' => [],
            'template' => 'example.latte',
            'expected' => [
                'data' => "Hello Mercure!\n",
                'topics' => ['test'],
            ],
        ];

        yield 'template from options' => [
            'data' => [
                'data' => 'Hello Mercure!',
            ],
            'options' => [
                'template' => 'example.latte',
            ],
            'template' => null,
            'expected' => [
                'data' => "Hello Mercure!\n",
                'topics' => ['test'],
            ],
        ];

        yield 'json template' => [
            'data' => [
                'id' => '1',
                'title' => 'Mercure',
            ],
            'options' => [],
            'template' => 'example.json.latte',
            'expected' => [
                'data' => Json::encode([
                    'id' => '1',
                    'title' => 'Mercure',
                ], pretty: true) . PHP_EOL,
                'topics' => ['test'],
            ],
        ];

        yield 'turbo stream' => [
            'data' => 'Hello Mercure!',
            'options' => [
                'action' => TurboStreamAction::Update,
            ],
            'template' => 'exampleStream.latte',
            'expected' => [
                'data' => "\tHello Mercure!\n",
                'topics' => ['test'],
                'sse_type' => 'turbo-stream',
            ],
        ];
    }

    /**
     * @dataProvider provideBroadcasts
     *
     * @param array<string, string>|string $data
     * @param array<string, mixed> $options
     * @param array<string, mixed> $expected
     */
    public function testBroadcast(array|string $data, array $options, ?string $template, array $expected): void
    {
        Assert::same(
            Json::encode($expected),
            $this->broadcaster->broadcast('test', $data, $options, $template),
        );
    }

    /**
     * @return iterable<string, array{
     *     options: array<string, mixed>,
     *     template: string,
     *     exception: class-string<\Throwable>,
     *     message: string,
     * }>
     */
    public function provideBroadcastErrors(): iterable
    {
        yield 'missing template' => [
            'options' => [],
            'template' => '/fixtures/inexistent.latte',
            'exception' => BroadcastException::class,
            'message' => \sprintf('Template file "%1$s/fixtures/inexistent.latte" not found in "%1$s".', self::TEMPLATES),
        ];

        yield 'turbo stream action without block' => [
            'options' => [
                'action' => TurboStreamAction::Remove,
            ],
            'template' => 'exampleStream.latte',
            'exception' => RuntimeException::class,
            'message' => "Cannot include undefined block 'remove'.",
        ];
    }

    /**
     * @dataProvider provideBroadcastErrors
     *
     * @param array<string, mixed> $options
     * @param class-string<\Throwable> $exception
     */
    public function testBroadcastError(array $options, string $template, string $exception, string $message): void
    {
        Assert::exception(
            fn (): string => $this->broadcaster->broadcast('test', 'Hello Mercure!', $options, $template),
            $exception,
            $message,
        );
    }

    public function testBroadcastStateIsExposed(): void
    {
        $this->broadcaster->broadcast('test', 'test');
        Assert::same([
            'rendered_data' => 'test',
        ], $this->broadcaster->broadcastOptions());

        $this->broadcaster->broadcast('test', [
            'data' => 'Hello Mercure!',
        ], template: 'example.latte');

        Assert::same(self::TEMPLATES, $this->templatePathResolver->basePath());
        Assert::same($this->broadcasterHub, $this->broadcaster->broadcasterHub());
        Assert::same('http://example.com/hub', $this->broadcaster->broadcasterUrl());
        Assert::same([
            'template' => $this->templatePathResolver->resolvedDir() . '/example.latte',
            'rendered_data' => 'Hello Mercure!' . PHP_EOL,
        ], $this->broadcaster->broadcastOptions());
    }
}

(new TemplatingBroadcasterTest())->run();
