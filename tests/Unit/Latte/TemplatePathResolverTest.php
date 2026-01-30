<?php

declare(strict_types=1);

namespace Tests\Unit\Raneomik\NetteMercure\Core;

require __DIR__ . '/../../bootstrap.php';

use Raneomik\NetteMercure\Exception\BroadcastException;
use Raneomik\NetteMercure\Latte\TemplatePathResolver;
use Tester\Assert;
use Tester\TestCase;

class TemplatePathResolverTest extends TestCase
{
	public function testConfigured(): void
	{
		$resolver = new TemplatePathResolver(dirname(__DIR__, 2) . '/fixtures/templates');

		Assert::same(
		    dirname(__DIR__, 2) . '/fixtures/templates/example.latte',
		    $resolver->resolve('/example.latte'),
		);

		Assert::exception(
		    fn(): string => $resolver->resolve('/inexistent.latte'),
		    BroadcastException::class,
		    sprintf('Template file "%1$s/inexistent.latte" not found in "%1$s".', $resolver->basePath()),
		);
	}

	public function testResolution(): void
	{
		$resolver = new TemplatePathResolver();

		Assert::same(
		    dirname(__DIR__, 2) . '/fixtures/templates/example.latte',
		    $resolver->resolve(dirname(__DIR__, 2) . '/fixtures/templates/example.latte'),
		);

		Assert::same(
		    dirname(__DIR__) . '/edge-fixture/example.latte',
		    $resolver->resolve('/edge-fixture/example.latte'),
		);

		Assert::same(
		    __DIR__ . '/../../fixtures/templates/example.latte',
		    $resolver->resolve('/../../fixtures/templates/example.latte'),
		);

		Assert::exception(
		    fn(): string => $resolver->resolve('/../../inexistent.latte'),
		    BroadcastException::class,
		    '~Template file "/../../inexistent\.latte" not found. Checked paths: ~',
		);
	}
}

(new TemplatePathResolverTest())->run();
