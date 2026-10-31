<?php

declare(strict_types=1);

namespace Tests\Unit\Raneomik\NetteMercure\Core\Publish\Latte;

require \dirname(__DIR__, 4) . '/bootstrap.php';

use Raneomik\NetteMercure\Core\Publish\Latte\TemplatePathResolver;
use Raneomik\NetteMercure\Exception\BroadcastException;
use Tester\Assert;
use Tester\TestCase;

/**
 * @testCase
 */
final class TemplatePathResolverTest extends TestCase
{
    private const string TEMPLATES = __DIR__ . '/../../../../fixtures/templates';

    /**
     * @return iterable<string, array{
     *     basePath: ?string,
     *     template: string,
     *     expected: string,
     * }>
     */
    public function provideResolutions(): iterable
    {
        yield 'relative to the configured path' => [
            'basePath' => self::TEMPLATES,
            'template' => '/example.latte',
            'expected' => self::TEMPLATES . '/example.latte',
        ];

        yield 'absolute path' => [
            'basePath' => null,
            'template' => self::TEMPLATES . '/example.latte',
            'expected' => self::TEMPLATES . '/example.latte',
        ];

        yield 'relative to the caller' => [
            'basePath' => null,
            'template' => '/edge-fixture/example.latte',
            'expected' => \dirname(__DIR__, 1) . '/edge-fixture/example.latte',
        ];

        yield 'parent directories' => [
            'basePath' => null,
            'template' => '/../../../../fixtures/templates/example.latte',
            'expected' => __DIR__ . '/../../../../fixtures/templates/example.latte',
        ];
    }

    /**
     * @dataProvider provideResolutions
     */
    public function testResolution(?string $basePath, string $template, string $expected): void
    {
        Assert::same($expected, $this->resolver($basePath)->resolve($template));
    }

    public function testResolvedDirOfAnAbsolutePath(): void
    {
        $resolver = $this->resolver(null);
        $resolver->resolve(self::TEMPLATES . '/example.latte');

        Assert::same(self::TEMPLATES . '/', $resolver->resolvedDir());
    }

    /**
     * @return iterable<string, array{
     *     basePath: ?string,
     *     template: string,
     *     message: string,
     * }>
     */
    public function provideMissingTemplates(): iterable
    {
        yield 'with a configured path' => [
            'basePath' => self::TEMPLATES,
            'template' => '/inexistent.latte',
            'message' => \sprintf('Template file "%1$s/inexistent.latte" not found in "%1$s".', self::TEMPLATES),
        ];

        yield 'without configured path' => [
            'basePath' => null,
            'template' => '/../../inexistent.latte',
            'message' => '~Template file "/../../inexistent\.latte" not found. Checked paths: ~',
        ];
    }

    /**
     * @dataProvider provideMissingTemplates
     */
    public function testMissingTemplate(?string $basePath, string $template, string $message): void
    {
        $resolver = $this->resolver($basePath);

        Assert::exception(
            static fn (): string => $resolver->resolve($template),
            BroadcastException::class,
            $message,
        );
    }

    /**
     * null: the default base path
     */
    private function resolver(?string $basePath): TemplatePathResolver
    {
        return null === $basePath ? new TemplatePathResolver() : new TemplatePathResolver($basePath);
    }
}

(new TemplatePathResolverTest())->run();
