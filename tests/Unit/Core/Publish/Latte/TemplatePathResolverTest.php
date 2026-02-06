<?php

declare(strict_types=1);

namespace Tests\Unit\Raneomik\NetteMercure\Latte;

require \dirname(__DIR__, 4).'/bootstrap.php';

use Raneomik\NetteMercure\Core\Publish\Latte\TemplatePathResolver;
use Raneomik\NetteMercure\Exception\BroadcastException;
use Tester\Assert;
use Tester\TestCase;

final class TemplatePathResolverTest extends TestCase
{
    /**
     * @testCase
     */
    public function testConfigured(): void
    {
        $resolver = new TemplatePathResolver(\dirname(__DIR__, 4).'/fixtures/templates');

        Assert::same(
            \dirname(__DIR__, 4).'/fixtures/templates/example.latte',
            $resolver->resolve('/example.latte'),
        );

        Assert::exception(
            static fn (): string => $resolver->resolve('/inexistent.latte'),
            BroadcastException::class,
            \sprintf('Template file "%1$s/inexistent.latte" not found in "%1$s".', $resolver->basePath()),
        );
    }

    /**
     * @testCase
     */
    public function testResolution(): void
    {
        $resolver = new TemplatePathResolver();

        Assert::same(
            \dirname(__DIR__, 4).'/fixtures/templates/example.latte',
            $resolver->resolve(\dirname(__DIR__, 4).'/fixtures/templates/example.latte'),
        );

        Assert::same(
            \dirname(__DIR__).'/edge-fixture/example.latte',
            $resolver->resolve('/edge-fixture/example.latte'),
        );

        Assert::same(
            __DIR__.'/../../../../fixtures/templates/example.latte',
            $resolver->resolve('/../../../../fixtures/templates/example.latte'),
        );

        Assert::exception(
            static fn (): string => $resolver->resolve('/../../inexistent.latte'),
            BroadcastException::class,
            '~Template file "/../../inexistent\.latte" not found. Checked paths: ~',
        );
    }
}

(new TemplatePathResolverTest())->run();
